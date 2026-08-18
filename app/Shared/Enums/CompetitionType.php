<?php

declare(strict_types=1);

namespace App\Shared\Enums;

enum CompetitionType: string
{
    case TOURNAMENT = 'tournament';
    case HEAD_TO_HEAD = 'head_to_head';
}
