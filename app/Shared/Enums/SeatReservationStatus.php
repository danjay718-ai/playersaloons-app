<?php

namespace App\Shared\Enums;

enum SeatReservationStatus: string
{
    case RESERVED = 'reserved';
    case CONFIRMED = 'confirmed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
}
