<?php

declare(strict_types=1);

namespace App\Modules\Team\Events;

use App\Shared\Events\DomainEvent;

final class TeamMemberJoined extends DomainEvent
{
    public function __construct(
        public readonly int $teamId,
        public readonly int $userId,
    ) {
        parent::__construct();
    }
}
