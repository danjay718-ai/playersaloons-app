<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserUnsuspended;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UnsuspendUserAction
{
    /**
     * Unsuspend a user account (SUSPENDED -> ACTIVE).
     *
     * @throws AuthorizationException
     */
    public function execute(User $target, User $actor): void
    {
        if (! $actor->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only admins may unsuspend users.');
        }

        DB::transaction(function () use ($target, $actor): void {
            $target->setAttribute('status', UserStatus::ACTIVE);
            $target->save();

            activity()
                ->causedBy($actor)
                ->performedOn($target)
                ->log('user_unsuspended');

            UserUnsuspended::dispatch((int) $target->getKey(), (int) $actor->getKey());
        });
    }
}
