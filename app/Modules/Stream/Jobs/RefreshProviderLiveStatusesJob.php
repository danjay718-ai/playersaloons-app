<?php

declare(strict_types=1);

namespace App\Modules\Stream\Jobs;

use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Stream\Support\ProviderLiveStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshProviderLiveStatusesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ProviderLiveStatusService $service): void
    {
        StreamChannel::query()->where('is_public', true)->whereNull('taken_down_at')->whereIn('provider', ['youtube', 'twitch', 'facebook'])->orderBy('id')->chunkById(100, function ($channels) use ($service): void {
            foreach ($channels as $channel) {
                $service->refresh($channel);
            }
        });
    }
}
