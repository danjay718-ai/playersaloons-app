<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use App\Shared\Events\DomainEvent;

final class PrizeAwarded extends DomainEvent
{
    public function __construct(
        public readonly int $walletId,
        public readonly int $tournamentId,
        public readonly int $prizeDistributionId,
        public readonly string $amount,
        public readonly int $rank,
    ) {
        parent::__construct();
    }
}
