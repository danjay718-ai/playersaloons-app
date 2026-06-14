<?php

namespace App\Shared\Enums;

enum MatchStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case IN_PROGRESS = 'in_progress';
    case RESULT_SUBMITTED = 'result_submitted';
    case WAITING_FOR_CONFIRMATION = 'waiting_for_confirmation';
    case COMPLETED = 'completed';
    case DISPUTED = 'disputed';
    case FORFEITED = 'forfeited';
}
