<?php

namespace App\Shared\Enums;

enum HeadToHeadDisputeResolution: string
{
    case PLAYER_A = 'player_a';
    case PLAYER_B = 'player_b';
    case REFUND = 'refund';
}
