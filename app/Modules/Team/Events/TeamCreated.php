<?php

declare(strict_types=1);

namespace App\Modules\Team\Events;

use App\Shared\Events\DomainEvent;

final class TeamCreated extends DomainEvent
{
    public function __construct(
        public readonly int $teamId,
        public readonly string $name,
        public readonly int $captainUserId,
    ) {
        parent::__construct();
    }
}
