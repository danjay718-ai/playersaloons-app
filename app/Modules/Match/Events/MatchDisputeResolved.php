<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use App\Shared\Events\DomainEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final class MatchDisputeResolved extends DomainEvent implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly int $disputeId,
        public readonly int $playableMatchId,
    ) {
        parent::__construct();
    }
}
