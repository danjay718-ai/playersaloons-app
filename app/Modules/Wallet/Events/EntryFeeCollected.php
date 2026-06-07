<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Events;

use App\Shared\Events\DomainEvent;

final class EntryFeeCollected extends DomainEvent
{
    public function __construct(
        public readonly int $walletId,
        public readonly int $tournamentId,
        public readonly int $registrationId,
        public readonly string $amount,
        public readonly int $ledgerEntryId,
    ) {
        parent::__construct();
    }
}
