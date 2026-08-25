<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentRegistrationClosed;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\PrizeCalculationService;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

class CloseRegistrationAction
{
    public function __construct(
        private readonly TournamentStateMachine $stateMachine,
        private readonly PrizeCalculationService $prizeCalculationService,
    ) {}

    /**
     * Close registration for a tournament (REGISTRATION_OPEN → REGISTRATION_CLOSED).
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(Tournament $tournament): Tournament
    {
        return DB::transaction(function () use ($tournament): Tournament {
            $this->stateMachine->transition($tournament, TournamentStatus::REGISTRATION_CLOSED);

            // Calculate before setting the lock marker so the service uses the
            // confirmed attendance projection, not the previous stored amount.
            $finalPrizePool = $this->prizeCalculationService->calculate($tournament)['prize_pool'];

            $lockedAt = now();
            $tournament->registrations()
                ->where('status', RegistrationStatus::CONFIRMED)
                ->whereNull('locked_at')
                ->update(['locked_at' => $lockedAt, 'updated_at' => $lockedAt]);
            $tournament->forceFill(['registration_locked_at' => $lockedAt])->save();

            // Lock the attendance-adjusted amount while retaining the advertised
            // pool for auditability and future tournament templates.
            $tournament->prize_pool = number_format($finalPrizePool, 2, '.', '');
            $tournament->save();

            $totalRegistrations = $tournament->registrations()->count();
            TournamentRegistrationClosed::dispatch((int) $tournament->getKey(), $totalRegistrations);

            return $tournament->fresh() ?? $tournament;
        });
    }
}
