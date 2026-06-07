<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Events\TournamentCancelled;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCancellation;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CancelTournamentAction
{
    public function __construct(
        private readonly TournamentStateMachine $stateMachine,
        private readonly WalletService $walletService,
    ) {}

    /**
     * Cancel a tournament and auto-refund entry fees to all confirmed participants.
     *
     * Cannot cancel ONGOING tournaments (per spec).
     *
     * @throws InvalidStateTransitionException
     * @throws \LogicException
     */
    public function execute(Tournament $tournament, User $actor, string $reason, ?string $notes = null): Tournament
    {
        return DB::transaction(function () use ($tournament, $actor, $reason, $notes): Tournament {
            $this->stateMachine->transition($tournament, TournamentStatus::CANCELLED);

            // Collect confirmed registrations that paid
            /** @var Collection<int, TournamentRegistration> $paid */
            $paid = TournamentRegistration::query()
                ->where('tournament_id', $tournament->getKey())
                ->where('status', RegistrationStatus::CONFIRMED)
                ->where('payment_status', PaymentStatus::PAID)
                ->get();

            // Mark all confirmed/pending registrations as cancelled
            TournamentRegistration::query()
                ->where('tournament_id', $tournament->getKey())
                ->whereIn('status', [RegistrationStatus::CONFIRMED->value, RegistrationStatus::PENDING->value])
                ->update(['status' => RegistrationStatus::CANCELLED]);

            // Create immutable cancellation record
            $cancellation = TournamentCancellation::query()->create([
                'tournament_id' => $tournament->getKey(),
                'cancelled_by' => $actor->getKey(),
                'reason' => $reason,
                'notes' => $notes,
                'affected_participant_count' => $paid->count(),
                'refund_required' => $paid->count() > 0,
                'created_at' => now(),
            ]);

            TournamentCancelled::dispatch(
                (int) $tournament->getKey(),
                (int) $cancellation->getKey(),
                $reason,
                $paid->count() > 0
            );

            return $tournament->fresh() ?? $tournament;
        });
    }
}
