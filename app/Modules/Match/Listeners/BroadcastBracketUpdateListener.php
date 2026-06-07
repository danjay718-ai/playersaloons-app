<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\TournamentBracketUpdated;
use App\Modules\Match\Models\GameMatch;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastBracketUpdateListener implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $tournamentId = null;

        if (property_exists($event, 'tournamentId')) {
            $tournamentId = $event->tournamentId;
        } elseif (property_exists($event, 'rematchMatchId')) {
            // MatchRematchCreated has originalMatchId and rematchMatchId
            /** @var GameMatch|null $match */
            $match = GameMatch::query()->find($event->rematchMatchId);
            if ($match !== null) {
                $tournamentId = $match->tournament_id;
            }
        }

        if ($tournamentId === null) {
            return;
        }

        broadcast(new TournamentBracketUpdated($tournamentId));
    }
}
