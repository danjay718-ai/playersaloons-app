<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCheckin;
use App\Modules\Tournament\Models\TournamentParticipant;
use App\Shared\Enums\CheckinStatus;
use App\Shared\Enums\RegistrationStatus;

final class AutoPrepareTournamentParticipantsAction
{
    /**
     * Convert every locked, confirmed registration into a ready participant.
     * Set-based inserts keep this idempotent and avoid one query per player.
     */
    public function execute(Tournament $tournament): void
    {
        $registrations = $tournament->registrations()
            ->where('status', RegistrationStatus::CONFIRMED)
            ->whereNotNull('locked_at')
            ->get(['id', 'user_id', 'team_id']);

        if ($registrations->isEmpty()) {
            return;
        }

        $now = now();
        $existingCheckins = TournamentCheckin::query()
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->pluck('registration_id')
            ->all();

        $checkins = $registrations->whereNotIn('id', $existingCheckins)->map(fn ($registration): array => [
            'registration_id' => $registration->id,
            'status' => CheckinStatus::CHECKED_IN->value,
            'checked_in_at' => $now,
            'created_at' => $now,
        ])->all();

        if ($checkins !== []) {
            TournamentCheckin::query()->insert($checkins);
        }

        $existingParticipants = TournamentParticipant::query()
            ->where('tournament_id', $tournament->getKey())
            ->whereIn('registration_id', $registrations->pluck('id'))
            ->pluck('registration_id')
            ->all();

        $participants = $registrations->whereNotIn('id', $existingParticipants)->map(fn ($registration): array => [
            'tournament_id' => $tournament->getKey(),
            'registration_id' => $registration->id,
            'user_id' => $registration->user_id,
            'team_id' => $registration->team_id,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($participants !== []) {
            TournamentParticipant::query()->insert($participants);
        }
    }
}
