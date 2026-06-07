<?php

declare(strict_types=1);

namespace App\Modules\Operations\Events;

use App\Shared\Events\DomainEvent;

final class SystemMaintenanceStarted extends DomainEvent
{
    public function __construct(
        public readonly string $message,
        public readonly int $startedBy,
    ) {
        parent::__construct();
    }
}
