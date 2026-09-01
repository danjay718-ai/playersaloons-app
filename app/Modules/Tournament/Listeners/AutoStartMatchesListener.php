<?php

namespace App\Modules\Tournament\Listeners;

use App\Modules\Match\Actions\StartMatchAction;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Events\TournamentStarted;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\MatchStatus;

class AutoStartMatchesListener
{
    public function __construct(private readonly StartMatchAction $startMatchAction) {}

    public function handle(TournamentStarted $event): void
    {
        $isV2 = (int) (Tournament::query()->whereKey($event->tournamentId)->value('workflow_version') ?? 1) === 2;
        $matches = GameMatch::query()
            ->where('tournament_id', $event->tournamentId)
            ->where('status', MatchStatus::READY)
            ->get();

        foreach ($matches as $match) {
            if ($isV2 && $match->player_a_registration_id !== null && $match->player_b_registration_id !== null) {
                $match->forceFill(['player_a_ready_at' => now(), 'player_b_ready_at' => now()])->save();
            }
            if ($match->player_a_ready_at !== null && $match->player_b_ready_at !== null) {
                $this->startMatchAction->execute($match);
            }
        }
    }
}
