<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Exceptions;

use RuntimeException;

class TournamentAlreadyRegisteredException extends RuntimeException
{
    public function __construct(int $userId, int $tournamentId)
    {
        parent::__construct(
            "User #{$userId} is already registered for tournament #{$tournamentId}."
        );
    }
}
