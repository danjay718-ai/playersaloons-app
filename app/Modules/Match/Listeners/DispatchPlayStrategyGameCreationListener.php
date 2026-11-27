<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Events\MatchStarted;
use App\Modules\Match\Jobs\CreatePlayStrategyMatchGameJob;
use App\Modules\Match\Models\GameMatch;

final class DispatchPlayStrategyGameCreationListener
{
    public function handle(MatchStarted|MatchRematchCreated $event): void
    {
        if (! config('features.playstrategy.enabled')) {
            return;
        }

        $matchId = $event instanceof MatchRematchCreated ? $event->rematchMatchId : $event->matchId;
        $match = GameMatch::query()->with('tournament.game')->find($matchId);

        if ($match === null || ! $match->isProviderManaged()) {
            return;
        }

        // Explicitly afterCommit() so PlayStrategy API is never called inside an uncommitted transaction.
        CreatePlayStrategyMatchGameJob::dispatch(
            (int) $match->id,
            max(1, (int) ($match->active_attempt_number ?? 1)),
        )->afterCommit();
    }
}
