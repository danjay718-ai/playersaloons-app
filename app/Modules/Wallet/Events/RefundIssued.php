<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use App\Shared\Events\DomainEvent;

final class RefundIssued extends DomainEvent
{
    public function __construct(
        public readonly int $walletId,
        public readonly int $refundId,
        public readonly int $tournamentId,
        public readonly string $amount,
    ) {
        parent::__construct();
    }
}
