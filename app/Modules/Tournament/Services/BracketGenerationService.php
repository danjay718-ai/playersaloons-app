<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Exceptions\InsufficientParticipantsException;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Str;

class BracketGenerationService
{
    /**
     * Generate brackets, rounds, and matches for a tournament.
     *
     * @throws InsufficientParticipantsException
     */
    public function generate(Tournament $tournament): Bracket
    {
        $participants = $tournament->participants()->orderBy('seed')->get();
        $participantCount = $participants->count();
        $minRequired = $tournament->min_participants ?? 2;

        if ($participantCount < $minRequired) {
            throw new InsufficientParticipantsException(
                $tournament->name,
                $participantCount,
                $minRequired
            );
        }

        // Determine next power of 2
        $p = 1;
        while ($p < $participantCount) {
            $p *= 2;
        }

        $totalRounds = (int) log($p, 2);

        // Create the Bracket
        $bracket = Bracket::query()->create([
            'tournament_id' => $tournament->getKey(),
            'generated_at'  => now(),
            'created_at'    => now(),
        ]);

        // Create the Rounds
        $roundModels = [];
        for ($r = 1; $r <= $totalRounds; $r++) {
            $roundModels[$r] = Round::query()->create([
                'bracket_id'   => $bracket->getKey(),
                'round_number' => $r,
                'created_at'   => now(),
            ]);
        }

        // Generate empty Match slots for all rounds
        $matchesByRound = [];
        for ($r = 1; $r <= $totalRounds; $r++) {
            $slotsInRound = $p / (2 ** $r);
            for ($j = 1; $j <= $slotsInRound; $j++) {
                $match = GameMatch::query()->create([
                    'uuid'          => Str::uuid()->toString(),
                    'tournament_id' => $tournament->getKey(),
                    'round_id'      => $roundModels[$r]->getKey(),
                    'status'        => MatchStatus::PENDING,
                ]);
                $matchesByRound[$r][$j] = $match;
            }
        }

        // Seed Round 1 matches
        $byeMatchesCount = $p - $participantCount;
        $actualMatchesCount = (int) (($participantCount - $byeMatchesCount) / 2);

        // First, actual matches
        for ($i = 1; $i <= $actualMatchesCount; $i++) {
            /** @var GameMatch $match */
            $match = $matchesByRound[1][$i];

            $playerA = $participants[(2 * $i) - 2];
            $playerB = $participants[(2 * $i) - 1];

            $match->player_a_registration_id = $playerA->registration_id;
            $match->player_b_registration_id = $playerB->registration_id;
            $match->status = MatchStatus::READY;
            $match->save();
        }

        // Next, bye matches
        for ($i = 1; $i <= $byeMatchesCount; $i++) {
            /** @var GameMatch $match */
            $match = $matchesByRound[1][$actualMatchesCount + $i];

            $playerA = $participants[(2 * $actualMatchesCount) + $i - 1];

            $match->player_a_registration_id = $playerA->registration_id;
            $match->player_b_registration_id = null;
            $match->winner_registration_id = $playerA->registration_id;
            $match->status = MatchStatus::COMPLETED;
            $match->save();
        }

        // Propagate winners of bye matches forward
        for ($r = 1; $r < $totalRounds; $r++) {
            $slotsInRound = $p / (2 ** $r);
            for ($j = 1; $j <= $slotsInRound; $j++) {
                /** @var GameMatch $match */
                $match = $matchesByRound[$r][$j];
                $winnerId = $match->winner_registration_id;

                if ($winnerId !== null) {
                    $nextJ = (int) ceil($j / 2);
                    /** @var GameMatch $nextMatch */
                    $nextMatch = $matchesByRound[$r + 1][$nextJ];

                    if ($j % 2 !== 0) {
                        $nextMatch->player_a_registration_id = $winnerId;
                    } else {
                        $nextMatch->player_b_registration_id = $winnerId;
                    }

                    $nextMatch->save();

                    // If both players are now set in the next match, mark it READY
                    if ($nextMatch->player_a_registration_id !== null && $nextMatch->player_b_registration_id !== null) {
                        $nextMatch->status = MatchStatus::READY;
                        $nextMatch->save();
                    }
                }
            }
        }

        return $bracket;
    }
}
