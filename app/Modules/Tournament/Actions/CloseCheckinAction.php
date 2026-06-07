<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentCheckinClosed;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;

class CloseCheckinAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Close check-in for a tournament (CHECKIN_OPEN → CHECKIN_CLOSED).
     *
     * @throws \App\Shared\Exceptions\InvalidStateTransitionException
     */
    public function execute(Tournament $tournament): Tournament
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($tournament): Tournament {
            $this->stateMachine->transition($tournament, TournamentStatus::CHECKIN_CLOSED);

            // Get all confirmed registrations for the tournament
            $registrations = $tournament->registrations()
                ->where('status', \App\Shared\Enums\RegistrationStatus::CONFIRMED)
                ->get();

            foreach ($registrations as $registration) {
                // Check if they checked in
                $hasCheckin = \App\Modules\Tournament\Models\TournamentCheckin::query()
                    ->where('registration_id', $registration->getKey())
                    ->where('status', \App\Shared\Enums\CheckinStatus::CHECKED_IN)
                    ->exists();

                if (! $hasCheckin) {
                    // Mark checkin as missed
                    \App\Modules\Tournament\Models\TournamentCheckin::query()->create([
                        'registration_id' => $registration->getKey(),
                        'status'          => \App\Shared\Enums\CheckinStatus::MISSED,
                        'checked_in_at'   => null,
                        'created_at'      => now(),
                    ]);
                }
            }

            $confirmedCount = $tournament->participants()->count();
            TournamentCheckinClosed::dispatch((int) $tournament->getKey(), $confirmedCount);

            return $tournament->fresh() ?? $tournament;
        });
    }
}
