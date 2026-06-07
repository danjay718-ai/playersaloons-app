<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AssignRoleAction
{
    /**
     * Assign a Spatie role to a user.
     *
     * @throws AuthorizationException
     */
    public function execute(User $target, string $role, User $actor): void
    {
        if (! $actor->hasRole('SUPER_ADMIN')) {
            throw new AuthorizationException('Only SUPER_ADMIN may assign roles.');
        }

        DB::transaction(function () use ($target, $role, $actor): void {
            $target->assignRole($role);

            activity()
                ->causedBy($actor)
                ->performedOn($target)
                ->withProperties(['role' => $role])
                ->log('role_assigned');
        });
    }
}
