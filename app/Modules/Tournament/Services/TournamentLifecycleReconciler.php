<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Actions\CloseCheckinAction;
use App\Modules\Tournament\Actions\CloseRegistrationAction;
use App\Modules\Tournament\Actions\GenerateBracketAction;
use App\Modules\Tournament\Actions\OpenCheckinAction;
use App\Modules\Tournament\Actions\OpenRegistrationAction;
use App\Modules\Tournament\Actions\StartTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;

final class TournamentLifecycleReconciler
{
    public function __construct(
        private readonly OpenRegistrationAction $openRegistration,
        private readonly CloseRegistrationAction $closeRegistration,
        private readonly OpenCheckinAction $openCheckin,
        private readonly CloseCheckinAction $closeCheckin,
        private readonly GenerateBracketAction $generateBracket,
        private readonly StartTournamentAction $startTournament,
        private readonly CancelTournamentAction $cancelTournament,
    ) {}

    /**
     * Move one competition through every transition that is already due.
     *
     * A row lock makes the reconciliation idempotent across multiple workers.
     * The loop intentionally catches up after downtime instead of requiring each
     * minute-specific scheduler tick to have run successfully.
     */
    public function reconcile(int $tournamentId): Tournament
    {
        return DB::transaction(function () use ($tournamentId): Tournament {
            /** @var Tournament $tournament */
            $tournament = Tournament::query()->lockForUpdate()->findOrFail($tournamentId);

            for ($transitionCount = 0; $transitionCount < 8; $transitionCount++) {
                $transitioned = match ($tournament->status) {
                    TournamentStatus::PUBLISHED => $this->openRegistrationIfDue($tournament),
                    TournamentStatus::REGISTRATION_OPEN => $this->closeRegistrationIfDue($tournament),
                    TournamentStatus::REGISTRATION_CLOSED => $this->openCheckinIfDue($tournament),
                    TournamentStatus::CHECKIN_OPEN => $this->closeCheckinIfDue($tournament),
                    TournamentStatus::CHECKIN_CLOSED => $this->generateBracketIfDue($tournament),
                    TournamentStatus::BRACKET_GENERATED => $this->startIfDue($tournament),
                    default => false,
                };

                if (! $transitioned || in_array($tournament->status, [
                    TournamentStatus::ONGOING,
                    TournamentStatus::COMPLETED,
                    TournamentStatus::CANCELLED,
                    TournamentStatus::REFUNDED,
                ], true)) {
                    break;
                }

                $tournament->refresh();
            }

            return $tournament->fresh() ?? $tournament;
        }, 3);
    }

    private function openRegistrationIfDue(Tournament $tournament): bool
    {
        if ($tournament->registration_open_at === null || $tournament->registration_open_at->isFuture()) {
            return false;
        }

        $this->openRegistration->execute($tournament);

        return true;
    }

    private function closeRegistrationIfDue(Tournament $tournament): bool
    {
        if ($tournament->registration_close_at === null || $tournament->registration_close_at->isFuture()) {
            return false;
        }

        $this->closeRegistration->execute($tournament);

        return true;
    }

    private function openCheckinIfDue(Tournament $tournament): bool
    {
        if ($tournament->checkin_open_at === null || $tournament->checkin_open_at->isFuture()) {
            return false;
        }

        $this->openCheckin->execute($tournament);

        return true;
    }

    private function closeCheckinIfDue(Tournament $tournament): bool
    {
        if ($tournament->checkin_close_at === null || $tournament->checkin_close_at->isFuture()) {
            return false;
        }

        $participantCount = $tournament->participants()->count();

        if ($participantCount < $tournament->min_participants) {
            if (! $tournament->is_auto_cancel_underfilled) {
                // Keep the state actionable for an administrator. Silently
                // advancing would generate an invalid bracket; silently
                // cancelling would violate the explicit opt-in policy.
                return false;
            }

            $this->cancelTournament->execute(
                $tournament,
                null,
                'Auto-cancelled: minimum checked-in participants not met.',
            );

            return true;
        }

        $this->closeCheckin->execute($tournament);

        return true;
    }

    private function generateBracketIfDue(Tournament $tournament): bool
    {
        if ($tournament->start_at === null || $tournament->start_at->isFuture()) {
            return false;
        }

        $this->generateBracket->execute($tournament);

        return true;
    }

    private function startIfDue(Tournament $tournament): bool
    {
        if ($tournament->start_at === null || $tournament->start_at->isFuture()) {
            return false;
        }

        $this->startTournament->execute($tournament);

        return true;
    }
}
