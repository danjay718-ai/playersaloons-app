<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;

class TournamentPolicy
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
     * Determine whether the user can create tournaments.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('tournaments.create');
    }

    /**
     * Determine whether the user can publish the tournament.
     */
    public function publish(User $user, Tournament $tournament): bool
    {
        return $user->hasPermissionTo('tournaments.publish')
            && $tournament->created_by === $user->id;
    }

    /**
     * Determine whether the user can manage the tournament.
     */
    public function manage(User $user, Tournament $tournament): bool
    {
        return $user->hasPermissionTo('tournaments.manage')
            && $tournament->created_by === $user->id;
    }

    /**
     * Determine whether the user can cancel the tournament.
     */
    public function cancel(User $user, Tournament $tournament): bool
    {
        return $user->hasPermissionTo('tournaments.cancel')
            && $tournament->created_by === $user->id;
    }
}
