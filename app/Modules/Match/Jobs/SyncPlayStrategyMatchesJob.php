<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Identity\Models\ExternalProviderAccount;
use App\Modules\Match\Actions\SyncPlayStrategyMatchResultAction;
use App\Modules\Match\Models\ExternalMatchGame;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SyncPlayStrategyMatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SyncPlayStrategyMatchResultAction $sync): void
    {
        if (! config('features.playstrategy.enabled')) {
            return;
        }

        ExternalMatchGame::query()
            ->where('provider', ExternalProviderAccount::PROVIDER_PLAYSTRATEGY)
            ->where('status', ExternalMatchGame::STATUS_CREATED)
            ->whereNotNull('external_game_id')
            ->where(function ($query): void {
                $query->whereNull('next_poll_at')
                    ->orWhere('next_poll_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(50)
            ->pluck('id')
            ->each(fn (int $id) => $sync->execute($id));
    }
}
