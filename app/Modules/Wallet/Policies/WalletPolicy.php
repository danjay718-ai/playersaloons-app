<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Wallet;

class WalletPolicy
{
    /**
     * Perform pre-authorization checks.
     * unfreeze is SUPER_ADMIN only, so we only return true for SUPER_ADMIN.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('SUPER_ADMIN')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the wallet.
     */
    public function view(User $user, Wallet $wallet): bool
    {
        if ($user->hasRole('ADMIN') || $user->hasRole('FINANCE_OPERATOR') || $user->hasRole('SUPPORT_AGENT')) {
            return true;
        }

        return $user->id === $wallet->user_id && $user->hasPermissionTo('wallets.view');
    }

    /**
     * Determine whether the user can request a withdrawal.
     */
    public function requestWithdrawal(User $user, Wallet $wallet): bool
    {
        return $user->id === $wallet->user_id && $user->hasPermissionTo('wallets.request_withdrawal');
    }

    /**
     * Determine whether the user can freeze a wallet.
     */
    public function freeze(User $user, Wallet $wallet): bool
    {
        return $user->hasRole('ADMIN') && $user->hasPermissionTo('wallets.freeze');
    }

    /**
     * Determine whether the user can unfreeze a wallet (SUPER_ADMIN only).
     */
    public function unfreeze(User $user, Wallet $wallet): bool
    {
        return false;
    }
}
