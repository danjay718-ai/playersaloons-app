<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserSuspended;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SuspendUserAction
{
    /**
     * Suspend a user account (ACTIVE -> SUSPENDED).
     *
     * @throws AuthorizationException
     */
    public function execute(User $target, User $actor, string $reason): void
    {
        if (! $actor->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only admins may suspend users.');
        }

        DB::transaction(function () use ($target, $actor, $reason): void {
            $target->setAttribute('status', UserStatus::SUSPENDED);
            $target->save();

            activity()
                ->causedBy($actor)
                ->performedOn($target)
                ->withProperties(['reason' => $reason])
                ->log('user_suspended');

            UserSuspended::dispatch((int) $target->getKey(), $reason, (int) $actor->getKey());
        });
    }
}
