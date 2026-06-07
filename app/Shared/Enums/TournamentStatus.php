<?php

namespace App\Shared\Enums;

enum TournamentStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case REGISTRATION_OPEN = 'REGISTRATION_OPEN';
    case REGISTRATION_CLOSED = 'REGISTRATION_CLOSED';
    case CHECKIN_OPEN = 'CHECKIN_OPEN';
    case CHECKIN_CLOSED = 'CHECKIN_CLOSED';
    case BRACKET_GENERATED = 'BRACKET_GENERATED';
    case ONGOING = 'ONGOING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case REFUNDED = 'REFUNDED';
}
