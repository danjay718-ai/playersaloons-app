<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Listeners;

use App\Modules\Identity\Services\PlayerProgressionService;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RegistrationStatus;

final class AwardTournamentExperienceListener
{
    public function __construct(private readonly PlayerProgressionService $progression) {}

    public function handle(TournamentCompleted $event): void
    {
        $tournament = Tournament::query()
            ->with(['registrations.rosterMembers'])
            ->find($event->tournamentId);

        if ($tournament === null) {
            return;
        }

        $userIds = $tournament->registrations
            ->reject(fn ($registration) => in_array($registration->status, [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED], true))
            ->flatMap(fn ($registration) => collect([$registration->user_id])->merge($registration->rosterMembers->pluck('user_id')))
            ->filter()
            ->unique();

        foreach ($userIds as $userId) {
            $this->progression->awardTournamentCompletion((int) $userId, (int) $tournament->getKey());
        }
    }
}
