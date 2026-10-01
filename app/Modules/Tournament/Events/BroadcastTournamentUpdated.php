<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class BroadcastTournamentUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public string $queue = 'tournament';

    public function __construct(
        public readonly string $tournamentUuid,
        public readonly string $change,
        public readonly ?string $matchUuid = null,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('tournament.'.$this->tournamentUuid), new Channel('tournaments')];
    }

    public function broadcastAs(): string
    {
        return 'tournament.updated';
    }

    /** @return array{change: string, match_uuid: string|null} */
    public function broadcastWith(): array
    {
        return ['change' => $this->change, 'match_uuid' => $this->matchUuid];
    }
}
