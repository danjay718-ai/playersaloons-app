<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Jobs;

use App\Modules\Tournament\Actions\OpenCheckinAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OpenCheckinJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OpenCheckinAction $action): void
    {
        $tournaments = Tournament::query()->where('status', TournamentStatus::REGISTRATION_CLOSED)
            ->whereNotNull('checkin_open_at')
            ->where('checkin_open_at', '<=', now())
            ->get();

        /** @var Tournament $tournament */
        foreach ($tournaments as $tournament) {
            try {
                $action->execute($tournament);
                Log::info("Auto-opened checkin for tournament {$tournament->uuid}");
            } catch (\Throwable $e) {
                Log::error("Failed to auto-open checkin for tournament {$tournament->uuid}: {$e->getMessage()}");
            }
        }
    }
}
