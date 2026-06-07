<?php

declare(strict_types=1);

namespace App\Shared\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Base class for all domain events.
 *
 * Rules (per Architecture Baseline v1 Part 6):
 * - Events are facts — past tense, immutable after construction.
 * - Thin — carry identifiers only, never full Eloquent models.
 * - Services emit events after successful business logic.
 * - Listeners re-query data when needed.
 */
abstract class DomainEvent
{
    use Dispatchable, InteractsWithSockets;

    public readonly Carbon $occurredAt;

    public function __construct()
    {
        $this->occurredAt = Carbon::now();
    }
}
