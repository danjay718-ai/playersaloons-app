<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use LogicException;

class UnsuspendUserAction
{
    public function execute(User $target, User $actor): void
    {
        if ($target->status !== UserStatus::SUSPENDED) {
            throw new LogicException(__('Only suspended accounts can be unsuspended.'));
        }

        app(ChangeUserStatusAction::class)->execute($target, $actor, UserStatus::ACTIVE, __('Account restored by an administrator.'), UserStatus::SUSPENDED);
    }
}
