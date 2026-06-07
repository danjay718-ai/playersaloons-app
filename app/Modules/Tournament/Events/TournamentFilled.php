<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Events;

use App\Shared\Events\DomainEvent;

final class TournamentFilled extends DomainEvent
{
    public function __construct(
        public readonly int $tournamentId,
        public readonly int $maxParticipants,
    ) {
        parent::__construct();
    }
}
