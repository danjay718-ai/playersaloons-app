<?php

declare(strict_types=1);

namespace App\Modules\Match\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\MatchDispute;

class DisputePolicy
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
     * Determine whether the user can view the dispute.
     */
    public function view(User $user, MatchDispute $dispute): bool
    {
        if ($user->hasRole('TOURNAMENT_ORGANIZER') || $user->hasRole('SUPPORT_AGENT')) {
            return true;
        }

        $match = $dispute->match;
        $playerAId = $match->playerARegistration?->user_id;
        $playerBId = $match->playerBRegistration?->user_id;

        return $user->id === $playerAId || $user->id === $playerBId;
    }

    /**
     * Determine whether the user can open a dispute.
     */
    public function open(User $user): bool
    {
        return $user->hasPermissionTo('disputes.open');
    }

    /**
     * Determine whether the user can resolve the dispute.
     */
    public function resolve(User $user, MatchDispute $dispute): bool
    {
        return $user->hasPermissionTo('disputes.resolve');
    }
}
