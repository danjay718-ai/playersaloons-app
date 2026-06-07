<?php

declare(strict_types=1);

namespace App\Modules\Operations\Events;

use App\Shared\Events\DomainEvent;

final class SystemMaintenanceCompleted extends DomainEvent
{
    public function __construct(
        public readonly int $completedBy,
    ) {
        parent::__construct();
    }
}
