<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use RuntimeException;

class InvalidStateTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to, string $machine = '')
    {
        $context = $machine ? " in {$machine}" : '';
        parent::__construct(
            "Invalid state transition{$context}: [{$from}] → [{$to}]"
        );
    }
}
