<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class BroadcastMatchCompleted implements ShouldBroadcast
{
    /**
     * Create a new event instance.
     */
    public function __construct(public readonly string $matchUuid) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('match.'.$this->matchUuid),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'match.completed';
    }
}
