<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\BroadcastMatchCompleted;
use App\Modules\Match\Events\TournamentBracketUpdated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Events\BroadcastBracketUpdate;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Contracts\Queue\ShouldQueue;

class BroadcastBracketUpdateListener
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $tournamentId = null;
        $matchId = null;

        if (property_exists($event, 'matchId')) {
            $matchId = $event->matchId;
        }

        if (property_exists($event, 'tournamentId')) {
            $tournamentId = $event->tournamentId;
        } elseif (property_exists($event, 'rematchMatchId')) {
            // MatchRematchCreated has originalMatchId and rematchMatchId
            $matchId = $event->rematchMatchId;
            /** @var GameMatch|null $match */
            $match = GameMatch::query()->find($event->rematchMatchId);
            if ($match !== null) {
                $tournamentId = $match->tournament_id;
            }
        }

        // If we don't have tournamentId but have matchId, look up tournamentId
        if ($tournamentId === null && $matchId !== null) {
            /** @var GameMatch|null $match */
            $match = GameMatch::query()->find($matchId);
            if ($match !== null) {
                $tournamentId = $match->tournament_id;
            }
        }

        // 1. Broadcast bracket updates if tournament is found
        if ($tournamentId !== null) {
            /** @var Tournament|null $tournament */
            $tournament = Tournament::query()->find($tournamentId);
            if ($tournament !== null) {
                // Broadcast old event for backward compatibility
                broadcast(new TournamentBracketUpdated($tournamentId));

                // Broadcast new Phase 12 event on tournament.{uuid} channel
                broadcast(new BroadcastBracketUpdate($tournament->uuid));
            }
        }

        // 2. Broadcast match completion if matchId is found and it is completed/forfeited
        if ($matchId !== null) {
            /** @var GameMatch|null $match */
            $match = GameMatch::query()->find($matchId);
            if ($match !== null && in_array($match->status->value, ['completed', 'forfeited'])) {
                broadcast(new BroadcastMatchCompleted($match->uuid));
            }
        }
    }
}
