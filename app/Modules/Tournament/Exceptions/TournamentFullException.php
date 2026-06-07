<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Exceptions;

use RuntimeException;

class TournamentFullException extends RuntimeException
{
    public function __construct(string $tournamentName, int $maxParticipants)
    {
        parent::__construct(
            "Tournament '{$tournamentName}' is full (max: {$maxParticipants} participants)."
        );
    }
}
