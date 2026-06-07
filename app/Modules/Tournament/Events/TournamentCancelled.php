<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Events;

use App\Shared\Events\DomainEvent;

final class TournamentCancelled extends DomainEvent
{
    public function __construct(
        public readonly int $tournamentId,
        public readonly int $cancellationId,
        public readonly string $reason,
        public readonly bool $refundRequired,
    ) {
        parent::__construct();
    }
}
