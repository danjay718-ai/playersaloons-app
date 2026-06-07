<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Events;

use App\Shared\Events\DomainEvent;

final class TournamentSeatReleased extends DomainEvent
{
    public function __construct(
        public readonly int $tournamentId,
        public readonly int $registrationId,
        public readonly int $userId,
    ) {
        parent::__construct();
    }
}
