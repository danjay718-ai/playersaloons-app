<?php

declare(strict_types=1);

namespace App\Modules\Identity\StateMachines;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use App\Shared\StateMachines\AbstractStateMachine;

final class UserStateMachine extends AbstractStateMachine
{
    protected function transitions(): array
    {
        return [
            UserStatus::ACTIVE->value => [UserStatus::SUSPENDED->value, UserStatus::BANNED->value],
            UserStatus::SUSPENDED->value => [UserStatus::ACTIVE->value, UserStatus::BANNED->value],
            UserStatus::BANNED->value => [UserStatus::ACTIVE->value],
        ];
    }

    public function transition(User $user, UserStatus $to): void
    {
        $this->assertValidTransition($user->status, $to);
        $user->update(['status' => $to]);
    }
}
