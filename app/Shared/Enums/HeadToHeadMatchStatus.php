<?php

namespace App\Shared\Enums;

enum HeadToHeadMatchStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case WAITING_FOR_CONFIRMATION = 'waiting_for_confirmation';
    case DISPUTED = 'disputed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
}
