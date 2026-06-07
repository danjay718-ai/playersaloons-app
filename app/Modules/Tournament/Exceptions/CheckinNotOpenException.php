<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Exceptions;

use RuntimeException;

class CheckinNotOpenException extends RuntimeException
{
    public function __construct(string $tournamentName)
    {
        parent::__construct(
            "Check-in is not currently open for tournament '{$tournamentName}'."
        );
    }
}
