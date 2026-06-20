<?php

declare(strict_types=1);

namespace App\Modules\Match\StateMachines;

use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\MatchStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\StateMachines\AbstractStateMachine;
use Illuminate\Support\Carbon;

class MatchStateMachine extends AbstractStateMachine
{
    /**
     * Valid state transitions.
     *
     * Lifecycle: PENDING → READY → IN_PROGRESS → WAITING_FOR_CONFIRMATION → COMPLETED
     *
     * Dispute path:   IN_PROGRESS / WAITING_FOR_CONFIRMATION → DISPUTED → COMPLETED (admin resolves)
     * Forfeit path:   IN_PROGRESS / READY → FORFEITED  (terminal)
     *
     * @return array<string, string[]>
     */
    protected function transitions(): array
    {
        return [
            MatchStatus::PENDING->value => [
                MatchStatus::READY->value,
            ],
            MatchStatus::READY->value => [
                MatchStatus::IN_PROGRESS->value,
                MatchStatus::FORFEITED->value,
            ],
            MatchStatus::IN_PROGRESS->value => [
                MatchStatus::WAITING_FOR_CONFIRMATION->value,
                MatchStatus::DISPUTED->value,
                MatchStatus::FORFEITED->value,
            ],
            MatchStatus::WAITING_FOR_CONFIRMATION->value => [
                MatchStatus::COMPLETED->value,
                MatchStatus::DISPUTED->value,
            ],
            // Legacy status retained for older rows that predate WAITING_FOR_CONFIRMATION.
            MatchStatus::RESULT_SUBMITTED->value => [
                MatchStatus::COMPLETED->value,
                MatchStatus::DISPUTED->value,
            ],
            MatchStatus::DISPUTED->value => [
                MatchStatus::COMPLETED->value,
            ],
            MatchStatus::COMPLETED->value => [],
            MatchStatus::FORFEITED->value => [],
        ];
    }

    // -------------------------------------------------------------------------
    // Transition
    // -------------------------------------------------------------------------

    /**
     * Transition the match to a new status.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(GameMatch $match, MatchStatus $to): void
    {
        $this->assertValidTransition($match->status, $to);

        $now = Carbon::now();

        match ($to) {
            MatchStatus::IN_PROGRESS => $match->started_at = $now,
            MatchStatus::COMPLETED => $match->completed_at = $now,
            default => null,
        };

        $match->status = $to;
        $match->save();
    }
}
