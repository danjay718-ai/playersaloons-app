<?php

declare(strict_types=1);

namespace App\Modules\Match\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class BroadcastMatchResultSubmitted implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(
        public readonly string $matchUuid,
        public readonly ?string $resultDeadlineAt,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('match.'.$this->matchUuid)];
    }

    public function broadcastAs(): string
    {
        return 'match.result.submitted';
    }

    /** @return array{result_deadline_at: string|null} */
    public function broadcastWith(): array
    {
        return ['result_deadline_at' => $this->resultDeadlineAt];
    }
}
