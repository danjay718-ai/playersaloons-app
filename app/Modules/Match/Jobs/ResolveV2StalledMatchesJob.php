<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Services\V2StalledMatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ResolveV2StalledMatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(V2StalledMatchService $stalled): void
    {
        if (! config('features.tournament_v2.enabled')) {
            return;
        }
        GameMatch::query()->whereNotNull('stalled_deadline_at')->where('stalled_deadline_at', '<=', now())
            ->whereNotIn('status', ['completed', 'forfeited'])->orderBy('id')->pluck('id')
            ->each(fn (int $id) => $stalled->expire($id));
        GameMatch::query()->whereNotNull('round_deadline_at')->where('round_deadline_at', '<=', now())
            ->whereNotIn('status', ['completed', 'forfeited'])->orderBy('id')->pluck('id')
            ->each(fn (int $id) => $stalled->expireRoundDeadline($id));
        GameMatch::query()->whereNotNull('final_resolution_eligible_at')
            ->where('final_resolution_eligible_at', '<=', now())->whereNull('final_resolution_notified_at')
            ->whereNotIn('status', ['completed', 'forfeited'])->orderBy('id')->pluck('id')
            ->each(fn (int $id) => $stalled->escalateNoChampion($id));
    }
}
