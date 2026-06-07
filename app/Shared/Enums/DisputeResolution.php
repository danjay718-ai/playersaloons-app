<?php

namespace App\Shared\Enums;

enum DisputeResolution: string
{
    case PLAYER_A = 'player_a';
    case PLAYER_B = 'player_b';
    case REMATCH = 'rematch';
}
