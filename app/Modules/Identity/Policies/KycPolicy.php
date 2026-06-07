<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;

class KycPolicy
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
     * Determine whether the user can view the KYC submission.
     */
    public function view(User $user, KycSubmission $kyc): bool
    {
        if ($user->hasRole('KYC_REVIEWER') || $user->hasRole('SUPPORT_AGENT')) {
            return true;
        }

        return $user->id === $kyc->user_id;
    }

    /**
     * Determine whether the user can review the KYC submission.
     */
    public function review(User $user, KycSubmission $kyc): bool
    {
        return $user->hasPermissionTo('kyc.review');
    }

    /**
     * Determine whether the user can approve the KYC submission.
     */
    public function approve(User $user, KycSubmission $kyc): bool
    {
        return $user->hasPermissionTo('kyc.approve');
    }

    /**
     * Determine whether the user can reject the KYC submission.
     */
    public function reject(User $user, KycSubmission $kyc): bool
    {
        return $user->hasPermissionTo('kyc.reject');
    }
}
