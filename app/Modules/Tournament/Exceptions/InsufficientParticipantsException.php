<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Exceptions;

use RuntimeException;

class InsufficientParticipantsException extends RuntimeException
{
    public function __construct(string $tournamentName, int $actual, int $minRequired)
    {
        parent::__construct(
            "Tournament '{$tournamentName}' has insufficient confirmed/checked-in participants. Actual: {$actual}, Minimum required: {$minRequired}."
        );
    }
}
