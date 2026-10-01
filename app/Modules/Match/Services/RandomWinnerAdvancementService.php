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
                if ($tournament->status === TournamentStatus::ONGOING) {
                    $this->completeTournament->execute($tournament);
                }

                return;
            }

            $matches = $nextRound->matches()->orderBy('id')->lockForUpdate()->get();
            // Retried completion events must not allocate a second seat or restart a match.
            if ($matches->contains(fn (GameMatch $candidate): bool => $candidate->player_a_registration_id === $winnerId
                || $candidate->player_b_registration_id === $winnerId)) {
                return;
            }
            $available = $matches->filter(fn (GameMatch $candidate): bool => $candidate->status === MatchStatus::PENDING
                && ($candidate->player_a_registration_id === null || $candidate->player_b_registration_id === null));
            if ($available->isEmpty()) {
                throw new LogicException('No next-round slot is available for this winner.');
            }
            $waiting = $available->filter(fn (GameMatch $candidate): bool => $candidate->player_a_registration_id !== null
                || $candidate->player_b_registration_id !== null);
            // Pair with an already advanced winner first so any two finished
            // matches can start the next round, regardless of their source positions.
            $destination = ($waiting->isNotEmpty() ? $waiting : $available)->random();
            if ($destination->player_a_registration_id === null) {
                $destination->player_a_registration_id = $winnerId;
            } else {
                $destination->player_b_registration_id = $winnerId;
            }
            $destination->save();

            if ($destination->player_b_registration_id !== null) {
                $this->stateMachine->transition($destination, MatchStatus::READY);
                MatchCreated::dispatch((int) $destination->id, (int) $destination->tournament_id, (int) $destination->round_id);
            }
        }, 3);
    }
}
