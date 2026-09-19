<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class BroadcastMatchReadinessUpdated implements ShouldBroadcastNow
{
    public function __construct(public readonly string $matchUuid) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('match.'.$this->matchUuid)];
    }

    public function broadcastAs(): string
    {
        return 'match.readiness.updated';
    }
}
