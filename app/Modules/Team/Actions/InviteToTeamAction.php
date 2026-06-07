<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use App\Modules\Team\Models\TeamInvitation;
use App\Shared\Enums\TeamInvitationStatus;
use Illuminate\Support\Str;
use LogicException;

class InviteToTeamAction
{
    public function execute(Team $team, User $invitedUser, User $inviter): TeamInvitation
    {
        if ($team->members()->where('user_id', $invitedUser->id)->exists()) {
            throw new LogicException('User is already a member of this team.');
        }

        if ($team->invitations()->where('invited_user_id', $invitedUser->id)
            ->where('status', TeamInvitationStatus::PENDING)
            ->exists()) {
            throw new LogicException('User already has a pending invitation to this team.');
        }

        return TeamInvitation::create([
            'uuid' => Str::uuid()->toString(),
            'team_id' => $team->id,
            'invited_user_id' => $invitedUser->id,
            'invited_by_user_id' => $inviter->id,
            'status' => TeamInvitationStatus::PENDING,
            'expires_at' => now()->addDays(7),
        ]);
    }
}
