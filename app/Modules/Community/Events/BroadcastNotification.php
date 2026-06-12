<?php

declare(strict_types=1);

namespace App\Modules\Community\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class BroadcastNotification implements ShouldBroadcast
{
    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $notificationData
     */
    public function __construct(
        public readonly string $userUuid,
        public readonly array $notificationData
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userUuid),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.received';
    }
}
