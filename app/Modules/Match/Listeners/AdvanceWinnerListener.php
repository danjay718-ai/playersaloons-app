<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Modules\Tournament\Actions\CompleteTournamentAction;
use App\Modules\Tournament\Models\Round;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;

class AdvanceWinnerListener
{
    public function __construct(
        private readonly MatchStateMachine $stateMachine,
        private readonly CompleteTournamentAction $completeTournamentAction
    ) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        // Support both MatchCompleted and MatchForfeited
        if (! property_exists($event, 'matchId')) {
            return;
        }

        DB::transaction(function () use ($event) {
            /** @var GameMatch|null $match */
            $match = GameMatch::query()->find($event->matchId);

            if ($match === null) {
                return;
            }

            $winnerRegistrationId = $match->winner_registration_id;
            if ($winnerRegistrationId === null) {
                return;
            }

            $round = $match->round;
            $bracket = $round->bracket;

            /** @var Round|null $nextRound */
            $nextRound = Round::query()
                ->where('bracket_id', $bracket->id)
                ->where('round_number', $round->round_number + 1)
                ->first();

            if ($nextRound === null) {
                $tournament = $match->tournament;
                $this->completeTournamentAction->execute($tournament);

                return;
            }

            $matchesInRound = $round->matches()->orderBy('id')->get();
            $j = null;
            foreach ($matchesInRound as $index => $m) {
                if ($m->id === $match->id) {
                    $j = $index + 1;
                    break;
                }
            }

            if ($j === null) {
                return;
            }

            $nextMatches = $nextRound->matches()->orderBy('id')->get();
            $nextJ = (int) ceil($j / 2);

            /** @var GameMatch|null $nextMatch */
            $nextMatch = $nextMatches->get($nextJ - 1);

            if ($nextMatch === null) {
                return;
            }

            if ($j % 2 !== 0) {
                $nextMatch->player_a_registration_id = $winnerRegistrationId;
            } else {
                $nextMatch->player_b_registration_id = $winnerRegistrationId;
            }

            $nextMatch->save();

            if ($nextMatch->player_a_registration_id !== null && $nextMatch->player_b_registration_id !== null) {
                $this->stateMachine->transition($nextMatch, MatchStatus::READY);
                MatchCreated::dispatch((int) $nextMatch->getKey(), (int) $nextMatch->tournament_id, (int) $nextMatch->round_id);
            }
        });
    }
}
