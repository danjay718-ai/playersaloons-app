<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Team\Models\TeamInvitation;
use App\Modules\Team\Models\TeamMember;
use App\Shared\Enums\TeamInvitationStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

class AcceptTeamInvitationAction
{
    public function execute(TeamInvitation $invitation): void
    {
        if ($invitation->status !== TeamInvitationStatus::PENDING) {
            throw new LogicException('Only pending invitations can be accepted.');
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            throw new LogicException('This invitation has expired.');
        }

        DB::transaction(function () use ($invitation) {
            $invitation->status = TeamInvitationStatus::ACCEPTED;
            $invitation->save();

            TeamMember::create([
                'team_id' => $invitation->team_id,
                'user_id' => $invitation->invited_user_id,
                'role' => 'member',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        });
    }
}
