<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jobs;

use App\Modules\Identity\Actions\ApplyPendingDisputeBanAction;
use App\Modules\Identity\Models\PlayerDisputeStrike;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ApplyPendingDisputeBansJob implements ShouldQueue
{
    use Queueable;

    public function handle(ApplyPendingDisputeBanAction $action): void
    {
        PlayerDisputeStrike::query()->where('ban_pending', true)->select('id')
            ->chunkById(100, function ($strikes) use ($action): void {
                foreach ($strikes as $strike) {
                    $action->execute((int) $strike->id);
                }
            });
    }
}
