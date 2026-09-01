<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Tournament\Actions\AwardV2PrizesAction;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class AwardV2PrizesListener implements ShouldQueueAfterCommit
{
    public string $queue = 'wallet';

    public int $tries = 5;

    public function __construct(private readonly AwardV2PrizesAction $prizes) {}

    public function handle(TournamentCompleted $event): void
    {
        $tournament = Tournament::query()->find($event->tournamentId);
        if ($tournament !== null && (int) $tournament->workflow_version === 2) {
            $this->prizes->execute($tournament);
        }
    }
}
