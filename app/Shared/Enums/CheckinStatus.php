<?php

namespace App\Shared\Enums;

enum CheckinStatus: string
{
    case OPEN = 'open';
    case CHECKED_IN = 'checked_in';
    case MISSED = 'missed';
}
