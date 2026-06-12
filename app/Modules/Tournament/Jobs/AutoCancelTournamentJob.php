<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Jobs;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoCancelTournamentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CancelTournamentAction $cancelAction): void
    {
        $tournaments = Tournament::query()->where('status', TournamentStatus::CHECKIN_CLOSED)->get();

        /** @var Tournament $tournament */
        foreach ($tournaments as $tournament) {
            $participantCount = $tournament->participants()->count();
            $minRequired = $tournament->min_participants ?? 2;

            if ($participantCount < $minRequired) {
                /** @var User|null $systemUser */
                $systemUser = User::query()->where('email', 'platform@playersaloons.com')->first();

                if ($systemUser === null) {
                    continue;
                }

                try {
                    $cancelAction->execute(
                        $tournament,
                        $systemUser,
                        'Insufficient confirmed participants after check-in closed'
                    );
                    Log::info("Auto-cancelled tournament {$tournament->uuid} due to insufficient participants.");
                } catch (\Throwable $e) {
                    Log::error("Failed to auto-cancel tournament {$tournament->uuid}: {$e->getMessage()}");
                }
            }
        }
    }
}
