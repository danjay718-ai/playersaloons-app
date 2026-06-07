<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Jobs;

use App\Modules\Tournament\Actions\CloseRegistrationAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CloseRegistrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CloseRegistrationAction $action): void
    {
        $tournaments = Tournament::query()->where('status', TournamentStatus::REGISTRATION_OPEN)
            ->whereNotNull('registration_close_at')
            ->where('registration_close_at', '<=', now())
            ->get();

        /** @var Tournament $tournament */
        foreach ($tournaments as $tournament) {
            try {
                $action->execute($tournament);
                Log::info("Auto-closed registration for tournament {$tournament->uuid}");
            } catch (\Throwable $e) {
                Log::error("Failed to auto-close registration for tournament {$tournament->uuid}: {$e->getMessage()}");
            }
        }
    }
}
