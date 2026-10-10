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

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PUBLISHED => __('Published'),
            self::REGISTRATION_OPEN => __('Registration open'),
            self::REGISTRATION_CLOSED => __('Registration closed'),
            self::CHECKIN_OPEN => __('Check-in open'),
            self::CHECKIN_CLOSED => __('Check-in closed'),
            self::BRACKET_GENERATED => __('Bracket generated'),
            self::ONGOING => __('Ongoing'),
            self::COMPLETED => __('Completed'),
            self::CANCELLED => __('Cancelled'),
            self::REFUNDED => __('Refunded'),
        };
    }
}
