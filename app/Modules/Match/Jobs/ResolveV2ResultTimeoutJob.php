<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Actions\ResolveV2ResultTimeoutAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ResolveV2ResultTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $attemptId) {}

    public function handle(ResolveV2ResultTimeoutAction $resolveTimeout): void
    {
        if (config('features.tournament_v2.enabled')) {
            $resolveTimeout->execute($this->attemptId);
        }
    }
}
