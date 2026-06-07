<?php

declare(strict_types=1);

namespace App\Modules\Match\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;

class MatchPolicy
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
     * Determine whether the user can start a match.
     */
    public function start(User $user, GameMatch $match): bool
    {
        return $user->hasPermissionTo('matches.manage');
    }

    /**
     * Determine whether the user can submit result for a match.
     */
    public function submitResult(User $user, GameMatch $match): bool
    {
        if ($user->hasPermissionTo('matches.manage')) {
            return true;
        }

        $playerAId = $match->playerARegistration?->user_id;
        $playerBId = $match->playerBRegistration?->user_id;

        return ($user->id === $playerAId || $user->id === $playerBId)
            && $user->hasPermissionTo('matches.submit_result');
    }

    /**
     * Determine whether the user can open dispute for a match.
     */
    public function dispute(User $user, GameMatch $match): bool
    {
        if ($user->hasPermissionTo('matches.manage')) {
            return true;
        }

        $playerAId = $match->playerARegistration?->user_id;
        $playerBId = $match->playerBRegistration?->user_id;

        return ($user->id === $playerAId || $user->id === $playerBId)
            && $user->hasPermissionTo('disputes.open');
    }
}
