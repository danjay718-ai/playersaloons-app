<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Identity\Services\PlayerProgressionService;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class AwardV2ChampionExperienceListener implements ShouldQueueAfterCommit
{
    public string $queue = 'tournament';

    public function __construct(private readonly PlayerProgressionService $progression) {}

    public function handle(TournamentCompleted $event): void
    {
        $tournament = Tournament::query()->with(['registrations.rosterMembers', 'brackets.rounds.matches'])->find($event->tournamentId);
        if ($tournament === null || (int) $tournament->workflow_version !== 2) {
            return;
        }
        $finalMatch = $tournament->brackets->flatMap->rounds->sortByDesc('round_number')->flatMap->matches
            ->first(fn ($match) => $match->winner_registration_id !== null);
        $winner = $finalMatch === null ? null : $tournament->registrations->firstWhere('id', $finalMatch->winner_registration_id);
        if ($winner === null || $finalMatch->started_at === null
            || ! $finalMatch->attempts()->whereHas('submissions', fn ($submissions) => $submissions->where('registration_id', $winner->id))->exists()) {
            return;
        }

        collect([$winner->user_id])->merge($winner->rosterMembers->pluck('user_id'))->filter()->unique()
            ->each(function ($userId) use ($tournament): void {
                $this->progression->awardV2TournamentParticipation((int) $userId, (int) $tournament->id);
                $this->progression->awardTournamentChampion((int) $userId, (int) $tournament->id, (int) ($tournament->winner_bonus_xp ?? 0));
            });
    }
}
