<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Identity\Services\PlayerProgressionService;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Models\GameMatch;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class AwardV2EliminationExperienceListener implements ShouldQueueAfterCommit
{
    public string $queue = 'tournament';

    public function __construct(private readonly PlayerProgressionService $progression) {}

    public function handle(MatchCompleted $event): void
    {
        $match = GameMatch::query()->with([
            'tournament', 'attempts.submissions',
            'playerARegistration.rosterMembers', 'playerBRegistration.rosterMembers',
        ])->find($event->matchId);
        if ($match === null || (int) $match->tournament->workflow_version !== 2) {
            return;
        }

        $loser = (int) $match->winner_registration_id === (int) $match->player_a_registration_id
            ? $match->playerBRegistration
            : $match->playerARegistration;
        if ($loser === null) {
            return; // BYE / no real opponent.
        }

        $attempt = $match->attempts->firstWhere('attempt_number', $match->active_attempt_number);
        $loserPlayed = $attempt?->submissions->contains('registration_id', $loser->id) === true;
        if (! $loserPlayed && ! in_array($match->resolution_reason, ['confirmed_submissions', 'admin_resolution'], true)) {
            return; // No-show and timeout participants do not earn participation XP.
        }

        collect([$loser->user_id])->merge($loser->rosterMembers->pluck('user_id'))
            ->filter()->unique()
            ->each(fn ($userId) => $this->progression->awardV2TournamentParticipation(
                (int) $userId,
                (int) $match->tournament_id,
            ));
    }
}
