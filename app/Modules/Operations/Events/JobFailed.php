<?php

declare(strict_types=1);

namespace App\Modules\Operations\Events;

use App\Shared\Events\DomainEvent;

final class JobFailed extends DomainEvent
{
    public function __construct(
        public readonly string $jobName,
        public readonly string $errorMessage,
        public readonly int $jobExecutionLogId,
    ) {
        parent::__construct();
    }
}
