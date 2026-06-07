<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TournamentBracketUpdated implements ShouldBroadcast
{
    /**
     * Create a new event instance.
     */
    public function __construct(public readonly int $tournamentId) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('tournaments.'.$this->tournamentId),
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
