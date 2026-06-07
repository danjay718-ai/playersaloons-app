<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentRegistrationClosed;
use App\Modules\Tournament\Events\TournamentCheckinOpened;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;

class CloseRegistrationAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Close registration for a tournament (REGISTRATION_OPEN → REGISTRATION_CLOSED).
     *
     * @throws \App\Shared\Exceptions\InvalidStateTransitionException
     */
    public function execute(Tournament $tournament): Tournament
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($tournament): Tournament {
            $this->stateMachine->transition($tournament, TournamentStatus::REGISTRATION_CLOSED);

            // Calculate final prize pool
            $paidCount = $tournament->registrations()
                ->where('status', \App\Shared\Enums\RegistrationStatus::CONFIRMED)
                ->where('payment_status', \App\Shared\Enums\PaymentStatus::PAID)
                ->count();

            $entryFee = (float) ($tournament->entry_fee ?? '0.00');
            $totalFees = $paidCount * $entryFee;

            $rakeSetting = \App\Modules\Operations\Models\SystemSetting::query()
                ->where('key', 'platform.rake_percentage')
                ->value('value');

            $rakePercentage = $rakeSetting !== null ? (float) $rakeSetting : 10.0;
            $prizePool = $totalFees * (1.0 - ($rakePercentage / 100.0));

            $tournament->prize_pool = number_format($prizePool, 2, '.', '');
            $tournament->save();

            $totalRegistrations = $tournament->registrations()->count();
            TournamentRegistrationClosed::dispatch((int) $tournament->getKey(), $totalRegistrations);

            return $tournament->fresh() ?? $tournament;
        });
    }
}
