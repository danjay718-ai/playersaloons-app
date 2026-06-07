<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Exceptions;

use RuntimeException;

class WalletSuspendedException extends RuntimeException
{
    public function __construct(string $walletUuid)
    {
        parent::__construct("Wallet is suspended and cannot perform debits. Wallet UUID: {$walletUuid}");
    }
}
