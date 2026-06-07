<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Withdrawal;

class WithdrawalPolicy
{
    /**
     * Determine whether the user can view the withdrawal.
     */
    public function view(User $user, Withdrawal $withdrawal): bool
    {
        if ($user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN') || $user->hasRole('FINANCE_OPERATOR') || $user->hasRole('SUPPORT_AGENT')) {
            return true;
        }

        return $user->id === $withdrawal->user_id;
    }

    /**
     * Determine whether the user can review the withdrawal (four-eyes enforced).
     */
    public function review(User $user, Withdrawal $withdrawal): bool
    {
        if ($user->hasRole('SUPER_ADMIN') && $user->id !== $withdrawal->user_id) {
            return true;
        }

        return $user->hasPermissionTo('withdrawals.review') && $user->id !== $withdrawal->user_id;
    }

    /**
     * Determine whether the user can approve the withdrawal (four-eyes enforced).
     */
    public function approve(User $user, Withdrawal $withdrawal): bool
    {
        if ($user->hasRole('SUPER_ADMIN') && $user->id !== $withdrawal->user_id) {
            return true;
        }

        return $user->hasPermissionTo('withdrawals.approve') && $user->id !== $withdrawal->user_id;
    }

    /**
     * Determine whether the user can reject the withdrawal (four-eyes enforced).
     */
    public function reject(User $user, Withdrawal $withdrawal): bool
    {
        if ($user->hasRole('SUPER_ADMIN') && $user->id !== $withdrawal->user_id) {
            return true;
        }

        return $user->hasPermissionTo('withdrawals.reject') && $user->id !== $withdrawal->user_id;
    }
}
