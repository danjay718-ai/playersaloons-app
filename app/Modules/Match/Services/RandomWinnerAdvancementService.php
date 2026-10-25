<?php

declare(strict_types=1);

namespace App\Modules\Match\Services;

use App\Modules\Match\Events\MatchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Modules\Tournament\Actions\CompleteTournamentAction;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

final class RandomWinnerAdvancementService
{
    public function __construct(
        private readonly MatchStateMachine $stateMachine,
        private readonly CompleteTournamentAction $completeTournament,
    ) {}

    public function advance(GameMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            // Serialize allocation across all rounds, including simultaneous results.
            $tournament = Tournament::query()->lockForUpdate()->findOrFail($match->tournament_id);
            $source = GameMatch::query()->with('round')->lockForUpdate()->findOrFail($match->id);
            $winnerId = $source->winner_registration_id;
            if ($winnerId === null || ! in_array($source->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)) {
                return;
            }

            $nextRound = Round::query()->where('bracket_id', $source->round->bracket_id)
                ->where('round_number', $source->round->round_number + 1)->first();
            if ($nextRound === null) {
                if ($tournament->status === TournamentStatus::ONGOING || (int) $tournament->workflow_version !== 2) {
                    $this->completeTournament->execute($tournament);
                }

                return;
            }

            $sources = $source->round->matches()->orderBy('id')->get();
            $winnerIds = collect([$winnerId]);
            if ($sources->count() % 2 !== 0) {
                // Wait for every winner before choosing a fair BYE; started matches cannot be reseated.
                if ($sources->contains(fn (GameMatch $candidate): bool => $candidate->winner_registration_id === null
                    || ! in_array($candidate->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true))) {
                    return;
                }
                $winnerIds = $sources->pluck('winner_registration_id')->shuffle();
                $byeCounts = GameMatch::query()->where('tournament_id', $tournament->id)
                    ->where('status', MatchStatus::COMPLETED)->whereNull('player_b_registration_id')
                    ->whereIn('winner_registration_id', $winnerIds)
                    ->whereHas('round', fn ($query) => $query->where('round_number', '<', $nextRound->round_number))
                    ->selectRaw('winner_registration_id, COUNT(*) as total')
                    ->groupBy('winner_registration_id')->pluck('total', 'winner_registration_id');
                $byeWinnerId = $winnerIds->sortBy(fn ($id) => $byeCounts[$id] ?? 0)->first();
                $winnerIds = $winnerIds->reject(fn ($id) => $id === $byeWinnerId)->push($byeWinnerId);
            }

            foreach ($winnerIds->values() as $winnerIndex => $advancingId) {
                $matches = $nextRound->matches()->orderBy('id')->lockForUpdate()->get();
                if ($matches->contains(fn (GameMatch $candidate): bool => $candidate->player_a_registration_id === $advancingId
                    || $candidate->player_b_registration_id === $advancingId)) {
                    continue;
                }
                $byeMatchId = $sources->count() % 2 !== 0 ? $matches->last()?->id : null;
                $available = $matches->filter(fn (GameMatch $candidate): bool => $candidate->status === MatchStatus::PENDING
                    && ($candidate->player_a_registration_id === null || $candidate->player_b_registration_id === null));
                $pairedSlots = $available->filter(fn (GameMatch $candidate): bool => $candidate->id !== $byeMatchId);
                if ($pairedSlots->isNotEmpty()) {
                    $available = $pairedSlots;
                }
                if ($available->isEmpty()) {
                    throw new LogicException('No next-round slot is available for this winner.');
                }
                $waiting = $available->filter(fn (GameMatch $candidate): bool => $candidate->player_a_registration_id !== null
                    || $candidate->player_b_registration_id !== null);
                if ((int) $tournament->workflow_version === 2) {
                    $destination = ($waiting->isNotEmpty() ? $waiting : $available)->random();
                    $seatA = $destination->player_a_registration_id === null;
                } else {
                    $sourceIndex = $sources->count() % 2 !== 0 ? $winnerIndex : $sources->search(fn ($candidate) => $candidate->id === $source->id);
                    $destination = $matches->get(intdiv($sourceIndex, 2));
                    $seatA = $sourceIndex % 2 === 0;
                    // Preserve seats allocated before deployment, including an already awarded BYE.
                    if ($destination === null || ! $available->contains('id', $destination->id)
                        || ($seatA ? $destination->player_a_registration_id : $destination->player_b_registration_id) !== null) {
                        $destination = ($waiting->isNotEmpty() ? $waiting : $available)->first();
                        $seatA = $destination->player_a_registration_id === null;
                    }
                }
                if ($seatA) {
                    $destination->player_a_registration_id = $advancingId;
                } else {
                    $destination->player_b_registration_id = $advancingId;
                }
                $destination->save();

                if ($destination->id === $byeMatchId) {
                    $destination->winner_registration_id = $advancingId;
                    $destination->status = MatchStatus::COMPLETED;
                    $destination->save();
                    $this->advance($destination);
                } elseif ($destination->player_a_registration_id !== null && $destination->player_b_registration_id !== null) {
                    $this->stateMachine->transition($destination, MatchStatus::READY);
                    MatchCreated::dispatch((int) $destination->id, (int) $destination->tournament_id, (int) $destination->round_id);
                }
            }
        }, 3);
    }
}
