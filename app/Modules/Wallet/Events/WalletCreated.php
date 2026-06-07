<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use App\Shared\Events\DomainEvent;

final class WalletCreated extends DomainEvent
{
    public function __construct(
        public readonly int $walletId,
        public readonly int $userId,
    ) {
        parent::__construct();
    }
}
