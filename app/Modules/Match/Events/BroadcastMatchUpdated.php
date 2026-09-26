<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class BroadcastMatchUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public string $queue = 'tournament';

    public function __construct(
        public readonly string $matchUuid,
        public readonly string $change,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('match.'.$this->matchUuid)];
    }

    public function broadcastAs(): string
    {
        return 'match.updated';
    }

    /** @return array{change: string} */
    public function broadcastWith(): array
    {
        return ['change' => $this->change];
    }
}
