<?php

declare(strict_types=1);

namespace App\Modules\Stream\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamMessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $streamChannelId;

    public int $messageId;

    public function __construct(int $streamChannelId, int $messageId)
    {
        $this->streamChannelId = $streamChannelId;
        $this->messageId = $messageId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('stream.'.$this->streamChannelId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StreamMessageDeleted';
    }
}
