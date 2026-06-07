<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Shared\Events\DomainEvent;

final class UserUnsuspended extends DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly int $unsuspendedBy,
    ) {
        parent::__construct();
    }
}
