<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use App\Shared\Events\DomainEvent;

final class WithdrawalRejected extends DomainEvent
{
    public function __construct(
        public readonly int $withdrawalId,
        public readonly int $walletId,
        public readonly int $rejectedBy,
        public readonly string $reason,
    ) {
        parent::__construct();
    }
}
