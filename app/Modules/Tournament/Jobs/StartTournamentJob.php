<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Jobs;

use App\Modules\Tournament\Actions\GenerateBracketAction;
use App\Modules\Tournament\Actions\StartTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartTournamentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GenerateBracketAction $generateBracketAction, StartTournamentAction $startTournamentAction): void
    {
        // Find tournaments ready to be bracketed and started
        $tournaments = Tournament::query()->whereIn('status', [TournamentStatus::CHECKIN_CLOSED, TournamentStatus::BRACKET_GENERATED])
            ->whereNotNull('start_at')
            ->where('start_at', '<=', now())
            ->get();

        /** @var Tournament $tournament */
        foreach ($tournaments as $tournament) {
            try {
                if ($tournament->status === TournamentStatus::CHECKIN_CLOSED) {
                    $generateBracketAction->execute($tournament);
                    $tournament->refresh();
                }

                if ($tournament->status === TournamentStatus::BRACKET_GENERATED) {
                    $startTournamentAction->execute($tournament);
                }

                Log::info("Auto-started tournament {$tournament->uuid}");
            } catch (\Throwable $e) {
                Log::error("Failed to auto-start tournament {$tournament->uuid}: {$e->getMessage()}");
            }
        }
    }
}
