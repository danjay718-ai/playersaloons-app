<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Team\Events\TeamMemberJoined;
use App\Modules\Team\Models\TeamInvitation;
use App\Modules\Team\Models\TeamMember;
use App\Modules\Team\StateMachines\InvitationStateMachine;
use App\Shared\Enums\TeamInvitationStatus;
use Illuminate\Support\Facades\DB;

class AcceptTeamInvitationAction
{
    public function __construct(private readonly InvitationStateMachine $stateMachine) {}

    public function execute(TeamInvitation $invitation): void
    {
        DB::transaction(function () use ($invitation) {
            $this->stateMachine->transition($invitation, TeamInvitationStatus::ACCEPTED);

            TeamMember::create([
                'team_id' => $invitation->team_id,
                'user_id' => $invitation->invited_user_id,
                'role' => 'member',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            TeamMemberJoined::dispatch($invitation->team_id, $invitation->invited_user_id);
        });
    }
}
