<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Exceptions;

use RuntimeException;

class TournamentNotOpenForRegistrationException extends RuntimeException
{
    public function __construct(string $tournamentName, string $currentStatus)
    {
        parent::__construct(
            "Tournament '{$tournamentName}' is not open for registration (status: {$currentStatus})."
        );
    }
}
