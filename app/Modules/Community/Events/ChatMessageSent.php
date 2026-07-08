<?php

declare(strict_types=1);

namespace App\Modules\Community\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ChatMessageSent implements ShouldBroadcastNow
{
    /**
     * @param  array<string, mixed>  $message
     */
    public function __construct(
        public readonly string $conversationUuid,
        public readonly array $message
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.'.$this->conversationUuid),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }
}
