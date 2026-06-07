<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Exceptions;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(string $walletUuid, string $amount, string $balance)
    {
        parent::__construct("Insufficient wallet balance. Wallet UUID: {$walletUuid}. Requested: {$amount}, Available: {$balance}");
    }
}
