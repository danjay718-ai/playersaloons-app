<?php

declare(strict_types=1);

namespace App\Modules\Team\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;

class TeamPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can create teams.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('teams.create');
    }

    /**
     * Determine whether the user can manage the team.
     */
    public function manage(User $user, Team $team): bool
    {
        return $user->id === $team->captain_user_id;
    }

    /**
     * Determine whether the user can invite members to the team.
     */
    public function invite(User $user, Team $team): bool
    {
        return $user->id === $team->captain_user_id;
    }

    /**
     * Determine whether the user can remove members from the team.
     */
    public function remove(User $user, Team $team): bool
    {
        return $user->id === $team->captain_user_id;
    }
}
