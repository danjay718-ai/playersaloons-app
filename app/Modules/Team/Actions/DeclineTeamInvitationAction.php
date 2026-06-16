<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Team\Models\TeamInvitation;
use App\Modules\Team\StateMachines\InvitationStateMachine;
use App\Shared\Enums\TeamInvitationStatus;

class DeclineTeamInvitationAction
{
    public function __construct(private readonly InvitationStateMachine $stateMachine) {}

    public function execute(TeamInvitation $invitation): void
    {
        $this->stateMachine->transition($invitation, TeamInvitationStatus::DECLINED);
    }
}
