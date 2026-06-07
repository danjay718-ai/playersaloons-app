<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class BroadcastTournamentCompleted implements ShouldBroadcast
{
    /**
     * Create a new event instance.
     */
    public function __construct(public readonly string $tournamentUuid) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tournament.'.$this->tournamentUuid),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'tournament.completed';
    }
}
