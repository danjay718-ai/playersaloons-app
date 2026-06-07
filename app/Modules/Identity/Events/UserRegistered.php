<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Shared\Events\DomainEvent;

final class UserRegistered extends DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly string $username,
    ) {
        parent::__construct();
    }
}
