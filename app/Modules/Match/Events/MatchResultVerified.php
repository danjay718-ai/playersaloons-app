<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use App\Shared\Events\DomainEvent;

final class MatchResultVerified extends DomainEvent
{
    public function __construct(
        public readonly int $matchId,
        public readonly int $winnerRegistrationId,
    ) {
        parent::__construct();
    }
}
