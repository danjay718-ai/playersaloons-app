<?php

declare(strict_types=1);

namespace App\Modules\Stream\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreamViewerCountUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $streamChannelId;

    public int $viewerCount;

    public function __construct(int $streamChannelId, int $viewerCount)
    {
        $this->streamChannelId = $streamChannelId;
        $this->viewerCount = $viewerCount;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('stream.'.$this->streamChannelId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StreamViewerCountUpdated';
    }
}
