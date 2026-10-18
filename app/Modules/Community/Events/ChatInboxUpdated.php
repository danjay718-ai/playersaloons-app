<?php

declare(strict_types=1);

namespace App\Modules\Community\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class ChatInboxUpdated implements ShouldBroadcastNow
{
    public function __construct(
        public readonly string $userUuid,
        public readonly string $conversationUuid,
        public readonly ?int $messageId = null,
        public readonly bool $alert = false,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userUuid)];
    }

    public function broadcastAs(): string
    {
        return 'chat.inbox.updated';
    }
}
