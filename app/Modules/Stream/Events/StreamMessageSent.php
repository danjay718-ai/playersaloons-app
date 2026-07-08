<?php

declare(strict_types=1);

namespace App\Modules\Stream\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $streamChannelId;
    /** @var array<string, mixed> */
    public array $message;

    /**
     * @param array<string, mixed> $message
     */
    public function __construct(int $streamChannelId, array $message)
    {
        $this->streamChannelId = $streamChannelId;
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('stream.' . $this->streamChannelId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StreamMessageSent';
    }
}
