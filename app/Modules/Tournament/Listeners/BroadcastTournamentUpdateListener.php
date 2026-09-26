<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Match\Events\BroadcastMatchUpdated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Events\BroadcastTournamentUpdated;
use App\Modules\Tournament\Models\Tournament;

final class BroadcastTournamentUpdateListener
{
    public function handle(object $event): void
    {
        $matchId = property_exists($event, 'matchId')
            ? (int) $event->matchId
            : (property_exists($event, 'rematchMatchId') ? (int) $event->rematchMatchId : null);
        $tournamentId = property_exists($event, 'tournamentId') ? (int) $event->tournamentId : null;
        $match = $matchId ? GameMatch::query()->find($matchId, ['id', 'uuid', 'tournament_id']) : null;
        $tournamentId ??= $match?->tournament_id;
        $tournament = $tournamentId ? Tournament::query()->find($tournamentId, ['id', 'uuid']) : null;

        if ($tournament === null) {
            return;
        }

        BroadcastTournamentUpdated::dispatch(
            (string) $tournament->uuid,
            class_basename($event),
            $match?->uuid === null ? null : (string) $match->uuid,
        );

        if ($match !== null) {
            BroadcastMatchUpdated::dispatch((string) $match->uuid, class_basename($event));
        }
    }
}
