<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use App\Shared\Events\DomainEvent;

final class WithdrawalRequested extends DomainEvent
{
    public function __construct(
        public readonly int $withdrawalId,
        public readonly int $walletId,
        public readonly int $userId,
        public readonly string $amount,
    ) {
        parent::__construct();
    }
}
