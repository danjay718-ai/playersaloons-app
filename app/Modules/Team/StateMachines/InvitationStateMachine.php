<?php

declare(strict_types=1);

namespace App\Modules\Team\StateMachines;

use App\Modules\Team\Models\TeamInvitation;
use App\Shared\Enums\TeamInvitationStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\StateMachines\AbstractStateMachine;

class InvitationStateMachine extends AbstractStateMachine
{
    /**
     * {@inheritDoc}
     *
     * Actual enum cases: PENDING, ACCEPTED, DECLINED, EXPIRED, REVOKED
     * (Note: the spec used CANCELLED but the actual enum uses REVOKED)
     */
    protected function transitions(): array
    {
        return [
            TeamInvitationStatus::PENDING->value => [
                TeamInvitationStatus::ACCEPTED->value,
                TeamInvitationStatus::DECLINED->value,
                TeamInvitationStatus::EXPIRED->value,
                TeamInvitationStatus::REVOKED->value,
            ],
            TeamInvitationStatus::ACCEPTED->value => [],
            TeamInvitationStatus::DECLINED->value => [],
            TeamInvitationStatus::EXPIRED->value => [],
            TeamInvitationStatus::REVOKED->value => [],
        ];
    }

    /**
     * Guard: the invitation must not be past its expiry date.
     *
     * @throws \LogicException
     */
    public function guardNotExpired(TeamInvitation $invitation): void
    {
        if ($invitation->expires_at !== null && $invitation->expires_at->isPast()) {
            throw new \LogicException('Cannot accept or decline an expired invitation.');
        }
    }

    /**
     * Transition a team invitation to a new status.
     *
     * @throws InvalidStateTransitionException
     * @throws \LogicException
     */
    public function transition(TeamInvitation $invitation, TeamInvitationStatus $to): void
    {
        $this->assertValidTransition($invitation->status, $to);

        if ($to === TeamInvitationStatus::ACCEPTED || $to === TeamInvitationStatus::DECLINED) {
            $this->guardNotExpired($invitation);
        }

        $invitation->status = $to;
        $invitation->save();
    }
}
