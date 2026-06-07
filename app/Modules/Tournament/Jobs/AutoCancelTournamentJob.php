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

class AutoCancelTournamentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $tournamentId) {}

    /**
     * Execute the job.
     */
    public function handle(CancelTournamentAction $cancelAction): void
    {
        /** @var Tournament|null $tournament */
        $tournament = Tournament::query()->find($this->tournamentId);

        if ($tournament === null) {
            return;
        }

        // Only auto-cancel if it's still in CHECKIN_CLOSED (or similar pre-bracket state)
        if ($tournament->status !== TournamentStatus::CHECKIN_CLOSED) {
            return;
        }

        $participantCount = $tournament->participants()->count();
        $minRequired = $tournament->min_participants ?? 2;

        if ($participantCount < $minRequired) {
            /** @var User|null $systemUser */
            $systemUser = User::query()->where('email', 'platform@playersaloons.com')->first();

            if ($systemUser === null) {
                throw new \RuntimeException('Platform system user not found.');
            }

            $cancelAction->execute(
                $tournament,
                $systemUser,
                'Insufficient confirmed participants after check-in closed'
            );
        }
    }
}
