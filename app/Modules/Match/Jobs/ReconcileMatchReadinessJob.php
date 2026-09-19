<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Services\MatchReadinessService;
use App\Shared\Enums\MatchStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ReconcileMatchReadinessJob implements ShouldQueue
{
    use Queueable;

    public function handle(MatchReadinessService $readiness): void
    {
        GameMatch::query()
            ->where('status', MatchStatus::READY)
            ->where(function ($due): void {
                $due->where('ready_deadline_at', '<=', now())
                    ->orWhere('extra_wait_deadline_at', '<=', now());
            })
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($matches) use ($readiness): void {
                foreach ($matches as $match) {
                    $readiness->reconcile($match);
                }
            });
    }
}
