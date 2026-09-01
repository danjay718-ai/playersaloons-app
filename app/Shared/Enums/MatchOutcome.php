<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum MatchOutcome: string
{
    case WIN = 'win';
    case LOSS = 'loss';
    case DRAW = 'draw';
}
