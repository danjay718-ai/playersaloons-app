<?php

declare(strict_types=1);

namespace App\Modules\Team\Events;

use App\Shared\Events\DomainEvent;

final class TeamCaptainChanged extends DomainEvent
{
    public function __construct(
        public readonly int $teamId,
        public readonly int $previousCaptainUserId,
        public readonly int $newCaptainUserId,
    ) {
        parent::__construct();
    }
}
