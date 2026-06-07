<?php

namespace App\Shared\Enums;

enum PaymentStatus: string
{
    case PAID = 'paid';
    case REFUNDED = 'refunded';
    case FREE = 'free';
}
