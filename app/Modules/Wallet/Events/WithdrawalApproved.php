<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use App\Shared\Events\DomainEvent;

final class WithdrawalApproved extends DomainEvent
{
    public function __construct(
        public readonly int $withdrawalId,
        public readonly int $walletId,
        public readonly int $approvedBy,
    ) {
        parent::__construct();
    }
}
