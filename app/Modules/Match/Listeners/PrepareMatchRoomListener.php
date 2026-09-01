<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Actions\StartMatchAction;
use App\Modules\Match\Events\MatchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Services\MatchReadinessService;
use App\Shared\Enums\TournamentStatus;

final class PrepareMatchRoomListener
{
    public function __construct(
        private readonly MatchReadinessService $readiness,
        private readonly StartMatchAction $startMatch,
    ) {}

    public function handle(MatchCreated $event): void
    {
        $match = GameMatch::query()->with('tournament')->find($event->matchId);
        if ($match !== null) {
            if ((int) $match->tournament->workflow_version === 2) {
                if ($match->tournament->status === TournamentStatus::ONGOING
                    && $match->player_a_registration_id !== null && $match->player_b_registration_id !== null) {
                    $match->forceFill(['player_a_ready_at' => now(), 'player_b_ready_at' => now()])->save();
                    $this->startMatch->execute($match);
                }

                return;
            }
            $this->readiness->prepare($match);
        }
    }
}
