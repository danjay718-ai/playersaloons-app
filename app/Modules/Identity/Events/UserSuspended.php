<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Shared\Events\DomainEvent;

final class UserSuspended extends DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly string $reason,
        public readonly int $suspendedBy,
    ) {
        parent::__construct();
    }
}
