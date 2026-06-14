<?php

namespace App\Modules\Tournament\Listeners;

use App\Modules\Match\Actions\StartMatchAction;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Events\TournamentStarted;
use App\Shared\Enums\MatchStatus;

class AutoStartMatchesListener
{
    public function __construct(private readonly StartMatchAction $startMatchAction) {}

    public function handle(TournamentStarted $event): void
    {
        $matches = GameMatch::query()
            ->where('tournament_id', $event->tournamentId)
            ->where('status', MatchStatus::READY)
            ->get();

        foreach ($matches as $match) {
            $this->startMatchAction->execute($match);
        }
    }
}
