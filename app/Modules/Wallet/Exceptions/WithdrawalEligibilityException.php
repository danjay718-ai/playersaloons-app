<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Exceptions;

use LogicException;

/** An expected withdrawal restriction with an explicitly safe player-facing message. */
final class WithdrawalEligibilityException extends LogicException
{
    public readonly string $userMessage;

    public function __construct(string $message, ?string $userMessage = null)
    {
        parent::__construct($message);
        $this->userMessage = $userMessage ?? $message;
    }
}
