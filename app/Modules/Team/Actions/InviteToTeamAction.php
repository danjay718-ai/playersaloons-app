<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Events\TeamMemberInvited;
use App\Modules\Team\Models\Team;
use App\Modules\Team\Models\TeamInvitation;
use App\Shared\Enums\TeamInvitationStatus;
use Illuminate\Support\Str;
use LogicException;

class InviteToTeamAction
{
    public function execute(Team $team, User $invitedUser, User $inviter): TeamInvitation
    {
        abort_unless((int) $team->captain_user_id === (int) $inviter->id, 403);
        if ($team->status !== 'active' || $invitedUser->status->value !== 'active' || ! $invitedUser->hasRole('PLAYER')) {
            throw new LogicException('This player is not available for squad invitations.');
        }
        if ($team->members()->where('user_id', $invitedUser->id)->where('status', 'active')->exists()) {
            throw new LogicException('User is already a member of this team.');
        }

        if ($team->invitations()->where('invited_user_id', $invitedUser->id)
            ->where('status', TeamInvitationStatus::PENDING)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists()) {
            throw new LogicException('This player already has a pending invitation to your squad.');
        }

        return tap(TeamInvitation::create([
            'uuid' => Str::uuid()->toString(),
            'team_id' => $team->id,
            'invited_user_id' => $invitedUser->id,
            'invited_by_user_id' => $inviter->id,
            'status' => TeamInvitationStatus::PENDING,
            'expires_at' => now()->addDays(7),
        ]), function (TeamInvitation $invitation) use ($team, $invitedUser, $inviter) {
            TeamMemberInvited::dispatch($invitation->id, $team->id, $invitedUser->id, $inviter->id);
        });
    }
}
