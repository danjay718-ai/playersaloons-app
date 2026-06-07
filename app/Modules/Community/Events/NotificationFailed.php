<?php

declare(strict_types=1);

namespace App\Modules\Community\Events;

use App\Shared\Events\DomainEvent;

final class NotificationFailed extends DomainEvent
{
    public function __construct(
        public readonly int $notificationId,
        public readonly int $userId,
        public readonly string $channel,
        public readonly string $reason,
    ) {
        parent::__construct();
    }
}
