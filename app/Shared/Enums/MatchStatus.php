<?php

namespace App\Shared\Enums;

enum MatchStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case IN_PROGRESS = 'in_progress';
    case RESULT_SUBMITTED = 'result_submitted';
    case COMPLETED = 'completed';
    case DISPUTED = 'disputed';
    case FORFEITED = 'forfeited';
}
