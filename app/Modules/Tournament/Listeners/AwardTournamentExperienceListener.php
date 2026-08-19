<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Identity\Services\PlayerProgressionService;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;

final class AwardTournamentExperienceListener
{
    public function __construct(private readonly PlayerProgressionService $progression) {}

    public function handle(TournamentCompleted $event): void
    {
        $tournament = Tournament::query()
            ->with(['registrations.rosterMembers', 'brackets.rounds.matches'])
            ->find($event->tournamentId);

        if ($tournament === null) {
            return;
        }

        $playedRegistrationIds = $tournament->brackets
            ->flatMap->rounds
            ->flatMap->matches
            ->filter(fn ($match) => $match->status === MatchStatus::COMPLETED && $match->started_at !== null)
            ->flatMap(fn ($match) => [$match->player_a_registration_id, $match->player_b_registration_id])
            ->filter()
            ->unique();

        $eligibleRegistrations = $tournament->registrations
            ->whereIn('id', $playedRegistrationIds)
            ->reject(fn ($registration) => in_array($registration->status, [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED], true));

        $userIds = $eligibleRegistrations
            ->flatMap(fn ($registration) => collect([$registration->user_id])->merge($registration->rosterMembers->pluck('user_id')))
            ->filter()
            ->unique();

        foreach ($userIds as $userId) {
            $this->progression->awardTournamentCompletion((int) $userId, (int) $tournament->getKey());
        }

        $finalMatch = $tournament->brackets
            ->flatMap->rounds
            ->sortByDesc('round_number')
            ->flatMap->matches
            ->first(fn ($match) => $match->winner_registration_id !== null);

        if ($finalMatch === null) {
            return;
        }

        $winner = $tournament->registrations->firstWhere('id', $finalMatch->winner_registration_id);
        if ($winner === null || ! $playedRegistrationIds->contains($winner->id)) {
            return;
        }

        collect([$winner->user_id])->merge($winner->rosterMembers->pluck('user_id'))
            ->filter()
            ->unique()
            ->each(fn ($userId) => $this->progression->awardTournamentChampion(
                (int) $userId,
                (int) $tournament->getKey(),
                (int) ($tournament->winner_bonus_xp ?? 0),
            ));
    }
}
