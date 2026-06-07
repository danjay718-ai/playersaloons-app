<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Events;

use App\Shared\Events\DomainEvent;

final class TournamentBracketGenerated extends DomainEvent
{
    public function __construct(
        public readonly int $tournamentId,
        public readonly int $bracketId,
        public readonly int $participantCount,
    ) {
        parent::__construct();
    }
}
