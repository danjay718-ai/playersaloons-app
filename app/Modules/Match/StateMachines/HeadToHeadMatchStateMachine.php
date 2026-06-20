<?php

declare(strict_types=1);

namespace App\Modules\Match\StateMachines;

use App\Modules\Match\Models\HeadToHeadMatch;
use App\Shared\Enums\HeadToHeadMatchStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\StateMachines\AbstractStateMachine;

class HeadToHeadMatchStateMachine extends AbstractStateMachine
{
    protected function transitions(): array
    {
        return [
            HeadToHeadMatchStatus::IN_PROGRESS->value => [
                HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION->value,
                HeadToHeadMatchStatus::CANCELLED->value,
                HeadToHeadMatchStatus::EXPIRED->value,
            ],
            HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION->value => [
                HeadToHeadMatchStatus::COMPLETED->value,
                HeadToHeadMatchStatus::DISPUTED->value,
            ],
            HeadToHeadMatchStatus::DISPUTED->value => [
                HeadToHeadMatchStatus::COMPLETED->value,
                HeadToHeadMatchStatus::CANCELLED->value,
            ],
            HeadToHeadMatchStatus::COMPLETED->value => [],
            HeadToHeadMatchStatus::CANCELLED->value => [],
            HeadToHeadMatchStatus::EXPIRED->value => [],
        ];
    }

    /**
     * @throws InvalidStateTransitionException
     */
    public function transition(HeadToHeadMatch $match, HeadToHeadMatchStatus $to): void
    {
        $this->assertValidTransition($match->status, $to);

        match ($to) {
            HeadToHeadMatchStatus::COMPLETED => $match->completed_at = now(),
            HeadToHeadMatchStatus::CANCELLED => $match->cancelled_at = now(),
            default => null,
        };

        $match->status = $to;
        $match->save();
    }
}
