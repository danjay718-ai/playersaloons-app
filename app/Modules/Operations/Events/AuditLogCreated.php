<?php

declare(strict_types=1);

namespace App\Modules\Operations\Events;

use App\Shared\Events\DomainEvent;

final class AuditLogCreated extends DomainEvent
{
    public function __construct(
        public readonly string $logId,
        public readonly string $event,
        public readonly int $causedByUserId,
        public readonly string $subjectType,
        public readonly int $subjectId,
    ) {
        parent::__construct();
    }
}
