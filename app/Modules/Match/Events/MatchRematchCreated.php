<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use App\Shared\Events\DomainEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final class MatchRematchCreated extends DomainEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly int $originalMatchId,
        public readonly int $rematchMatchId,
        public readonly string $originalMatchUuid,
        public readonly string $rematchMatchUuid,
    ) {
        parent::__construct();
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return collect([$this->originalMatchUuid, $this->rematchMatchUuid])
            ->unique()
            ->map(fn (string $uuid) => new PrivateChannel('match.'.$uuid))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'match.rematch.created';
    }

    /** @return array{rematch_uuid: string, same_match: bool} */
    public function broadcastWith(): array
    {
        return [
            'rematch_uuid' => $this->rematchMatchUuid,
            'same_match' => $this->originalMatchUuid === $this->rematchMatchUuid,
        ];
    }
}
