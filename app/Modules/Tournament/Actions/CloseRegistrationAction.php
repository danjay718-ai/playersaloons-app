<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Tournament\Events\TournamentRegistrationClosed;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

class CloseRegistrationAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Close registration for a tournament (REGISTRATION_OPEN → REGISTRATION_CLOSED).
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(Tournament $tournament): Tournament
    {
        return DB::transaction(function () use ($tournament): Tournament {
            $this->stateMachine->transition($tournament, TournamentStatus::REGISTRATION_CLOSED);

            // Calculate final prize pool
            $paidCount = $tournament->registrations()
                ->where('status', RegistrationStatus::CONFIRMED)
                ->where('payment_status', PaymentStatus::PAID)
                ->count();

            $entryFee = (float) ($tournament->entry_fee ?? '0.00');
            $totalFees = $paidCount * $entryFee;

            $rakeSetting = SystemSetting::query()
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
