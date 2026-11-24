<?php

declare(strict_types=1);

namespace App\Modules\Match\Exceptions;

final class PlayStrategyRateLimitException extends PlayStrategyException
{
    public function __construct(
        public readonly int $retryAfterSeconds = 60,
        string $message = 'PlayStrategy API rate limit reached.',
    ) {
        parent::__construct($message, 429);
    }
}
