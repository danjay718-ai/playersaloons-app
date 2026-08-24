<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferSuperAdminAction
{
    /**
     * Transfer the unique SUPER_ADMIN role ownership from the current Super Admin to a target user.
     *
     * @throws AuthorizationException|InvalidArgumentException
     */
    public function execute(User $target, User $actor): void
    {
        if (! $actor->hasRole('SUPER_ADMIN')) {
            throw new AuthorizationException('Only the current SUPER_ADMIN can transfer Super Admin ownership.');
        }

        if ($target->id === $actor->id) {
            throw new InvalidArgumentException('You are already the SUPER_ADMIN.');
        }

        DB::transaction(function () use ($target, $actor): void {
            // Assign SUPER_ADMIN to target
            $target->assignRole('SUPER_ADMIN');

            // Revoke SUPER_ADMIN from previous owner and ensure they keep ADMIN role
            $actor->removeRole('SUPER_ADMIN');
            if (! $actor->hasRole('ADMIN')) {
                $actor->assignRole('ADMIN');
            }

            activity()
                ->causedBy($actor)
                ->performedOn($target)
                ->withProperties([
                    'previous_super_admin_id' => $actor->id,
                    'new_super_admin_id' => $target->id,
                ])
                ->log('super_admin_transferred');
        });
    }
}
