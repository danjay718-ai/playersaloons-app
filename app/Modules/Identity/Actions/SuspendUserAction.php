<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;

class SuspendUserAction
{
    public function execute(User $target, User $actor, string $reason): void
    {
        app(ChangeUserStatusAction::class)->execute($target, $actor, UserStatus::SUSPENDED, $reason);
    }
}
