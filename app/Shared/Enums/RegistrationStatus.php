<?php

namespace App\Shared\Enums;

enum RegistrationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case REFUNDED = 'refunded';
    case CANCELLED = 'cancelled';
}
