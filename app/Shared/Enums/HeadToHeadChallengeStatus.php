<?php

namespace App\Shared\Enums;

enum HeadToHeadChallengeStatus: string
{
    case WAITING = 'waiting';
    case MATCHED = 'matched';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
}
