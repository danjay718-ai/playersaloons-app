<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Match\Events\MatchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Services\RandomWinnerAdvancementService;
use App\Modules\Tournament\Exceptions\InsufficientParticipantsException;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentParticipant;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BracketGenerationService
{
    public function __construct(private readonly RandomWinnerAdvancementService $randomAdvancement) {}

    /**
     * Generate brackets, rounds, and matches for a tournament.
     *
     * Uses checked-in participants (TournamentParticipant records, which are
     * created by CheckinParticipantAction) as the authoritative player list.
     * Pair every available participant; only an unpaired participant gets a bye.
     *
     * @throws InsufficientParticipantsException
     */
    public function generate(Tournament $tournament): Bracket
    {
        // ── Step 1: Resolve participants ───────────────────────────────────
        // TournamentParticipant rows only exist for checked-in players
        // (created by CheckinParticipantAction). We re-query ordered by seed
        // so that the GenerateBracketAction's shuffle + seed assignment is
        // respected. values() ensures 0-based sequential keys.
        /** @var Collection<int, TournamentParticipant> $participants */
        $participants = $tournament->participants()
            ->orderBy('seed')
            ->get()
            ->values();

        $participantCount = $participants->count();
        $minRequired = $tournament->min_participants ?? 2;

        if ($participantCount < $minRequired) {
            throw new InsufficientParticipantsException(
                $tournament->name,
                $participantCount,
                $minRequired
            );
        }

        // Each round contains pairs plus at most one unpaired participant.
        $slotsByRound = [];
        $advancingCount = $participantCount;
        $roundNumber = 1;
        while ($advancingCount > 1) {
            $advancingCount = (int) ceil($advancingCount / 2);
            $slotsByRound[$roundNumber] = $advancingCount;
            $roundNumber++;
        }
        $totalRounds = count($slotsByRound);
        $byeCount = $participantCount % 2;
        $actualMatchCount = intdiv($participantCount, 2);

        // ── Step 3: Create Bracket & Rounds ───────────────────────────────
        $bracket = Bracket::query()->create([
            'tournament_id' => $tournament->getKey(),
            'generated_at' => now(),
            'created_at' => now(),
        ]);

        /** @var array<int, Round> $roundModels */
        $roundModels = [];
        for ($r = 1; $r <= $totalRounds; $r++) {
            $roundModels[$r] = Round::query()->create([
                'bracket_id' => $bracket->getKey(),
                'round_number' => $r,
                'created_at' => now(),
            ]);
        }

        // ── Step 4: Pre-create all empty match slots ───────────────────────
        /** @var array<int, array<int, GameMatch>> $matchesByRound */
        $matchesByRound = [];
        for ($r = 1; $r <= $totalRounds; $r++) {
            $slotsInRound = $slotsByRound[$r];
            for ($j = 1; $j <= $slotsInRound; $j++) {
                $matchesByRound[$r][$j] = GameMatch::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'tournament_id' => $tournament->getKey(),
                    'round_id' => $roundModels[$r]->getKey(),
                    'status' => MatchStatus::PENDING,
                ]);
            }
        }

        // ── Step 5: Seed Round 1 — real matches ───────────────────────────
        // Players 0…(2*actualMatchCount - 1) fight in Round 1.
        // Pair adjacent seeds:
        //   Slot 1 → seed 1 vs seed 2
        //   Slot 2 → seed 3 vs seed 4  …etc.
        for ($i = 1; $i <= $actualMatchCount; $i++) {
            /** @var GameMatch $match */
            $match = $matchesByRound[1][$i];
            $playerA = $participants->get((2 * $i) - 2); // seed (2i-1)
            $playerB = $participants->get((2 * $i) - 1); // seed (2i)

            $match->player_a_registration_id = $playerA->registration_id;
            $match->player_b_registration_id = $playerB->registration_id;
            $match->status = MatchStatus::READY;
            $match->save();

            MatchCreated::dispatch(
                (int) $match->getKey(),
                (int) $tournament->getKey(),
                (int) $match->round_id
            );
        }

        // ── Step 6: Seed Round 1 — bye slots ──────────────────────────────
        // Only the remaining unpaired participant gets an automatic win.
        // Their bye slot is marked COMPLETED immediately; AdvanceWinnerListener
        // will propagate them into Round 2 in the next step.
        for ($i = 1; $i <= $byeCount; $i++) {
            /** @var GameMatch $match */
            $match = $matchesByRound[1][$actualMatchCount + $i];
            $playerA = $participants->get((2 * $actualMatchCount) + ($i - 1));

            $match->player_a_registration_id = $playerA->registration_id;
            $match->player_b_registration_id = null;
            $match->winner_registration_id = $playerA->registration_id;
            $match->status = MatchStatus::COMPLETED;
            $match->save();
        }

        if ((int) $tournament->workflow_version === 2) {
            foreach ($matchesByRound[1] as $match) {
                if ($match->winner_registration_id !== null) {
                    $this->randomAdvancement->advance($match);
                }
            }

            return $bracket;
        }

        // ── Step 7: Propagate bye winners into later rounds ────────────────
        // Walk every Round 1 slot. If a winner exists (bye or real),
        // place them into the correct slot of Round 2. If both players of a
        // Round 2 slot are now filled, mark it READY and dispatch MatchCreated.
        for ($r = 1; $r < $totalRounds; $r++) {
            $slotsInRound = $slotsByRound[$r];
            for ($j = 1; $j <= $slotsInRound; $j++) {
                /** @var GameMatch $match */
                $match = $matchesByRound[$r][$j];
                $winnerId = $match->winner_registration_id;

                if ($winnerId === null) {
                    continue;
                }

                $nextJ = (int) ceil($j / 2);
                /** @var GameMatch $nextMatch */
                $nextMatch = $matchesByRound[$r + 1][$nextJ];

                if ($j % 2 !== 0) {
                    $nextMatch->player_a_registration_id = $winnerId;
                } else {
                    $nextMatch->player_b_registration_id = $winnerId;
                }

                // The last winner has no opponent when this round has odd slots.
                if ($j === $slotsInRound && $slotsInRound % 2 !== 0) {
                    $nextMatch->winner_registration_id = $winnerId;
                    $nextMatch->status = MatchStatus::COMPLETED;
                }
                $nextMatch->save();

                if (
                    $nextMatch->player_a_registration_id !== null
                    && $nextMatch->player_b_registration_id !== null
                ) {
                    $nextMatch->status = MatchStatus::READY;
                    $nextMatch->save();

                    MatchCreated::dispatch(
                        (int) $nextMatch->getKey(),
                        (int) $tournament->getKey(),
                        (int) $nextMatch->round_id
                    );
                }
            }
        }

        return $bracket;
    }
}
