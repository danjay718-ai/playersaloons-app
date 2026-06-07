<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Events\PlayerCheckedIn;
use App\Modules\Tournament\Exceptions\CheckinNotOpenException;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCheckin;
use App\Modules\Tournament\Models\TournamentParticipant;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\CheckinStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;

class CheckinParticipantAction
{
    /**
     * Check in a participant for a tournament.
     *
     * Creates an immutable TournamentCheckin record and a TournamentParticipant.
     *
     * @throws CheckinNotOpenException
     * @throws \LogicException
     */
    public function execute(Tournament $tournament, User $user): TournamentCheckin
    {
        if ($tournament->status !== TournamentStatus::CHECKIN_OPEN) {
            throw new CheckinNotOpenException($tournament->name);
        }

        return DB::transaction(function () use ($tournament, $user): TournamentCheckin {
            /** @var TournamentRegistration|null $registration */
            $registration = TournamentRegistration::query()
                ->where('tournament_id', $tournament->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', RegistrationStatus::CONFIRMED)
                ->first();

            if ($registration === null) {
                throw new \LogicException('User does not have a confirmed registration for this tournament.');
            }

            // Idempotency: if already checked in, return existing record
            /** @var TournamentCheckin|null $existing */
            $existing = TournamentCheckin::query()
                ->where('registration_id', $registration->getKey())
                ->where('status', CheckinStatus::CHECKED_IN)
                ->first();

            if ($existing instanceof TournamentCheckin) {
                return $existing;
            }

            $checkin = TournamentCheckin::query()->create([
                'registration_id' => $registration->getKey(),
                'status'          => CheckinStatus::CHECKED_IN,
                'checked_in_at'   => now(),
                'created_at'      => now(),
            ]);

            // Upsert participant record
            TournamentParticipant::query()->updateOrCreate(
                [
                    'tournament_id'   => $tournament->getKey(),
                    'user_id'         => $user->getKey(),
                ],
                [
                    'registration_id' => $registration->getKey(),
                    'status'          => 'active',
                ]
            );

            PlayerCheckedIn::dispatch(
                (int) $tournament->getKey(),
                (int) $user->getKey(),
                (int) $checkin->getKey()
            );

            return $checkin;
        });
    }
}
