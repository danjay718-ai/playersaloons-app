<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Exceptions;

use RuntimeException;

class WalletFrozenException extends RuntimeException
{
    public function __construct(string $walletUuid)
    {
        parent::__construct("Wallet is frozen and cannot perform operations. Wallet UUID: {$walletUuid}");
    }
}
