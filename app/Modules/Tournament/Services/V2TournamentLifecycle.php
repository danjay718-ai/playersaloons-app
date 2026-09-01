<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Actions\AutoPrepareTournamentParticipantsAction;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Actions\FinalizeV2FinancialsAction;
use App\Modules\Tournament\Actions\GenerateBracketAction;
use App\Modules\Tournament\Actions\StartTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;

final class V2TournamentLifecycle
{
    public function __construct(
        private readonly AutoPrepareTournamentParticipantsAction $prepareParticipants,
        private readonly GenerateBracketAction $generateBracket,
        private readonly StartTournamentAction $startTournament,
        private readonly CancelTournamentAction $cancelTournament,
        private readonly FinalizeV2FinancialsAction $finalizeFinancials,
    ) {}

    public function reconcile(Tournament $tournament): Tournament
    {
        if (! config('features.tournament_v2.enabled')) {
            return $tournament;
        }

        return DB::transaction(function () use ($tournament): Tournament {
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            if ((int) $locked->workflow_version !== 2 || in_array($locked->status, [
                TournamentStatus::ONGOING,
                TournamentStatus::COMPLETED,
                TournamentStatus::CANCELLED,
                TournamentStatus::REFUNDED,
            ], true)) {
                return $locked;
            }

            $confirmed = $locked->registrations()->where('status', RegistrationStatus::CONFIRMED)->count();
            $now = now();
            $periodClosed = $locked->join_closes_at !== null && $now->greaterThanOrEqualTo($locked->join_closes_at);
            $startReached = $locked->start_at !== null && $now->greaterThanOrEqualTo($locked->start_at);
            $full = $confirmed >= (int) $locked->max_participants;

            // The Start Time is the cut-off for an occurrence. Empty and
            // under-minimum occurrences must immediately leave player-facing
            // registration; paid entries are refunded by the cancellation flow.
            if ($startReached && $confirmed < 2) {
                return $this->cancelTournament->execute(
                    $locked,
                    null,
                    $confirmed === 0
                        ? 'Automatically cancelled: no participants joined before the occurrence start time.'
                        : 'Automatically cancelled: the occurrence did not reach two participants before the start time.',
                );
            }

            if ($periodClosed && $confirmed < 2) {
                return $this->cancelTournament->execute(
                    $locked,
                    null,
                    'Automatically cancelled: the occurrence did not reach two participants before its period closed.',
                );
            }

            if ($startReached) {
                $locked->cancellationRequests()->where('status', 'pending')->update([
                    'status' => 'expired', 'resolved_at' => $now, 'updated_at' => $now,
                ]);
            }

            if (! $full && ! ($startReached && $confirmed >= 2)) {
                return $locked;
            }

            if ($locked->status === TournamentStatus::REGISTRATION_OPEN) {
                $lockedAt = now();
                $locked->registrations()
                    ->where('status', RegistrationStatus::CONFIRMED)
                    ->whereNull('locked_at')
                    ->update(['locked_at' => $lockedAt, 'updated_at' => $lockedAt]);
                $locked->forceFill([
                    'registration_locked_at' => $lockedAt,
                    'status' => TournamentStatus::CHECKIN_OPEN,
                ])->save();
                $this->prepareParticipants->execute($locked);
                $locked->forceFill(['status' => TournamentStatus::CHECKIN_CLOSED])->save();
                $this->finalizeFinancials->execute($locked);
                $this->generateBracket->execute($locked);
                $locked->refresh();
            }

            if ($startReached && $locked->status === TournamentStatus::BRACKET_GENERATED) {
                return $this->startTournament->execute($locked);
            }

            return $locked->fresh() ?? $locked;
        }, 3);
    }
}
