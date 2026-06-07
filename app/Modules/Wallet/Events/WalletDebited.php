<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use App\Shared\Events\DomainEvent;

final class WalletDebited extends DomainEvent
{
    public function __construct(
        public readonly int $walletId,
        public readonly int $ledgerEntryId,
        public readonly string $amount,
        public readonly string $type,
    ) {
        parent::__construct();
    }
}
