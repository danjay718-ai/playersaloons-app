<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use App\Shared\Events\DomainEvent;

final class MatchRematchCreated extends DomainEvent
{
    public function __construct(
        public readonly int $originalMatchId,
        public readonly int $rematchMatchId,
    ) {
        parent::__construct();
    }
}
