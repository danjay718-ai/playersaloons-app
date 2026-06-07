<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\Channel;

class TournamentBracketUpdated implements ShouldBroadcast
{
    /**
     * Create a new event instance.
     */
    public function __construct(public readonly int $tournamentId) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tournaments.' . $this->tournamentId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'bracket.updated';
    }
}
