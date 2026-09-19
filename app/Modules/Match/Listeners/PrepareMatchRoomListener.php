<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchCreated;
use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Services\MatchReadinessService;
use App\Shared\Enums\TournamentStatus;

final class PrepareMatchRoomListener
{
    public function __construct(private readonly MatchReadinessService $readiness) {}

    public function handle(MatchCreated|MatchRematchCreated $event): void
    {
        $matchId = $event instanceof MatchRematchCreated ? $event->rematchMatchId : $event->matchId;
        $match = GameMatch::query()->with('tournament')->find($matchId);
        if ($match !== null) {
            if ((int) $match->tournament->workflow_version === 2) {
                if ($match->tournament->status === TournamentStatus::ONGOING
                    && $match->player_a_registration_id !== null && $match->player_b_registration_id !== null) {
                    $this->readiness->prepare($match);
                }

                return;
            }
            $this->readiness->prepare($match);
        }
    }
}
