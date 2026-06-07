<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Tournament\Events\BroadcastTournamentCompleted;
use App\Modules\Tournament\Events\BroadcastTournamentStarted;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Events\TournamentStarted;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastTournamentLifecycleListener implements ShouldQueue
{
    /**
     * Queue the listener on the 'tournament' queue.
     */
    public string $queue = 'tournament';

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof TournamentStarted) {
            $tournament = Tournament::query()->find($event->tournamentId);
            if ($tournament !== null) {
                broadcast(new BroadcastTournamentStarted($tournament->uuid));
            }
        } elseif ($event instanceof TournamentCompleted) {
            $tournament = Tournament::query()->find($event->tournamentId);
            if ($tournament !== null) {
                broadcast(new BroadcastTournamentCompleted($tournament->uuid));
            }
        }
    }
}
