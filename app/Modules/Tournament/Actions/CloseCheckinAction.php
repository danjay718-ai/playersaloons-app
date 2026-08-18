<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentCheckinClosed;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCheckin;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\CheckinStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

class CloseCheckinAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Close check-in for a tournament (CHECKIN_OPEN → CHECKIN_CLOSED).
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(Tournament $tournament): Tournament
    {
        return DB::transaction(function () use ($tournament): Tournament {
            $this->stateMachine->transition($tournament, TournamentStatus::CHECKIN_CLOSED);

            // Resolve missed check-ins in two set-based queries. The previous
            // per-registration exists() loop produced an N+1 at peak check-in.
            $missedRegistrationIds = $tournament->registrations()
                ->where('status', RegistrationStatus::CONFIRMED)
                ->whereDoesntHave('checkins', fn ($query) => $query->where('status', CheckinStatus::CHECKED_IN))
                ->pluck('id');

            if ($missedRegistrationIds->isNotEmpty()) {
                $now = now();
                TournamentCheckin::query()->insert($missedRegistrationIds->map(fn (int $registrationId): array => [
                    'registration_id' => $registrationId,
                    'status' => CheckinStatus::MISSED->value,
                    'checked_in_at' => null,
                    'created_at' => $now,
                ])->all());
            }

            $confirmedCount = $tournament->participants()->count();
            TournamentCheckinClosed::dispatch((int) $tournament->getKey(), $confirmedCount);

            return $tournament->fresh() ?? $tournament;
        });
    }
}
