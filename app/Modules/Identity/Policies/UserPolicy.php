<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;

class UserPolicy
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
     * Determine whether the user can suspend another user.
     */
    public function suspend(User $user): bool
    {
        return $user->hasPermissionTo('users.suspend');
    }

    /**
     * Determine whether the user can unsuspend another user.
     */
    public function unsuspend(User $user): bool
    {
        return $user->hasPermissionTo('users.unsuspend');
    }

    /**
     * Determine whether the user can assign roles to another user.
     */
    public function assignRole(User $user): bool
    {
        return $user->hasPermissionTo('users.assign_role');
    }

    /**
     * Determine whether the user can revoke roles from another user.
     */
    public function revokeRole(User $user): bool
    {
        return $user->hasPermissionTo('users.revoke_role');
    }
}
