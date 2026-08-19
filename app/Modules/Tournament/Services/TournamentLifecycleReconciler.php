<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Actions\AutoPrepareTournamentParticipantsAction;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Actions\CloseCheckinAction;
use App\Modules\Tournament\Actions\CloseRegistrationAction;
use App\Modules\Tournament\Actions\GenerateBracketAction;
use App\Modules\Tournament\Actions\OpenCheckinAction;
use App\Modules\Tournament\Actions\OpenRegistrationAction;
use App\Modules\Tournament\Actions\StartTournamentAction;
use App\Modules\Tournament\Events\TournamentExtraRegistrationStarted;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RegistrationStatus;
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
        private readonly AutoPrepareTournamentParticipantsAction $autoPrepareParticipants,
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

        $confirmedCount = $tournament->registrations()
            ->where('status', RegistrationStatus::CONFIRMED)
            ->count();

        if ($confirmedCount < (int) $tournament->min_participants) {
            $extraMinutes = (int) ($tournament->extra_registration_minutes ?? 0);

            if ($extraMinutes > 0 && $tournament->extra_registration_started_at === null) {
                $now = now();
                $newDeadline = $now->copy()->addMinutes($extraMinutes);

                // Shift downstream estimates by exactly the one-time extension.
                // Existing entries are immediately locked and cannot leave.
                $tournament->registrations()
                    ->where('status', RegistrationStatus::CONFIRMED)
                    ->whereNull('locked_at')
                    ->update(['locked_at' => $now, 'updated_at' => $now]);

                $tournament->forceFill([
                    'extra_registration_started_at' => $now,
                    'registration_close_at' => $newDeadline,
                    'checkin_open_at' => $newDeadline,
                    'checkin_close_at' => $newDeadline,
                    'start_at' => $tournament->start_at?->copy()->addMinutes($extraMinutes),
                    'end_at' => $tournament->end_at?->copy()->addMinutes($extraMinutes),
                ])->save();

                TournamentExtraRegistrationStarted::dispatch((int) $tournament->getKey(), $extraMinutes);

                return true;
            }

            $this->cancelTournament->execute(
                $tournament,
                null,
                'Automatically cancelled: minimum registrations were not reached after Extra Registration Time.',
            );

            return true;
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
        $this->autoPrepareParticipants->execute($tournament);

        return true;
    }

    private function closeCheckinIfDue(Tournament $tournament): bool
    {
        if ($tournament->checkin_close_at === null || $tournament->checkin_close_at->isFuture()) {
            return false;
        }

        $participantCount = $tournament->participants()->count();

        if ($participantCount < $tournament->min_participants) {
            $this->cancelTournament->execute(
                $tournament,
                null,
                'Automatically cancelled: minimum locked registrations were not met.',
            );

            return true;
        }

        $this->closeCheckin->execute($tournament);

        return true;
    }

    private function generateBracketIfDue(Tournament $tournament): bool
    {
        // Generate Match Rooms immediately after entries lock. Their individual
        // Get Ready timers can run before the tournament's first-match time.
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
