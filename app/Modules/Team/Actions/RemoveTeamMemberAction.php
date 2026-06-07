<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use LogicException;

class RemoveTeamMemberAction
{
    public function execute(Team $team, User $user): void
    {
        if ($team->captain_user_id === $user->id) {
            throw new LogicException('Cannot remove the team captain. Transfer captaincy first.');
        }

        $member = $team->members()->where('user_id', $user->id)->first();

        if (! $member) {
            throw new LogicException('User is not a member of this team.');
        }

        $member->delete();
    }
}
