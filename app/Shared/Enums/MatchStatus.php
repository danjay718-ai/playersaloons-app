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

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::READY => __('Ready'),
            self::IN_PROGRESS => __('In progress'),
            self::RESULT_SUBMITTED => __('Result submitted'),
            self::WAITING_FOR_CONFIRMATION => __('Waiting for confirmation'),
            self::COMPLETED => __('Completed'),
            self::DISPUTED => __('Disputed'),
            self::FORFEITED => __('Forfeited'),
        };
    }
}
