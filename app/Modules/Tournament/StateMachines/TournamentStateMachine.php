<?php

declare(strict_types=1);

namespace App\Modules\Tournament\StateMachines;

use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\StateMachines\AbstractStateMachine;
use Illuminate\Support\Facades\Auth;
use LogicException;

class TournamentStateMachine extends AbstractStateMachine
{
    /**
     * Valid state transitions.
     *
     * Lifecycle: DRAFT → PUBLISHED → REGISTRATION_OPEN → REGISTRATION_CLOSED
     *            → CHECKIN_OPEN → CHECKIN_CLOSED → BRACKET_GENERATED
     *            → ONGOING → COMPLETED
     *
     * Cancellation is allowed up to BRACKET_GENERATED.
     * ONGOING tournaments CANNOT be cancelled per spec.
     *
     * @return array<string, string[]>
     */
    protected function transitions(): array
    {
        return [
            TournamentStatus::DRAFT->value => [
                TournamentStatus::PUBLISHED->value,
                TournamentStatus::CANCELLED->value,
            ],
            TournamentStatus::PUBLISHED->value => [
                TournamentStatus::REGISTRATION_OPEN->value,
                TournamentStatus::DRAFT->value,
                TournamentStatus::CANCELLED->value,
            ],
            TournamentStatus::REGISTRATION_OPEN->value => [
                TournamentStatus::REGISTRATION_CLOSED->value,
                TournamentStatus::PUBLISHED->value,
                TournamentStatus::CANCELLED->value,
            ],
            TournamentStatus::REGISTRATION_CLOSED->value => [
                TournamentStatus::CHECKIN_OPEN->value,
                TournamentStatus::REGISTRATION_OPEN->value,
                TournamentStatus::CANCELLED->value,
            ],
            TournamentStatus::CHECKIN_OPEN->value => [
                TournamentStatus::CHECKIN_CLOSED->value,
                TournamentStatus::REGISTRATION_CLOSED->value,
                TournamentStatus::CANCELLED->value,
            ],
            TournamentStatus::CHECKIN_CLOSED->value => [
                TournamentStatus::BRACKET_GENERATED->value,
                TournamentStatus::CHECKIN_OPEN->value,
                TournamentStatus::CANCELLED->value,
            ],
            TournamentStatus::BRACKET_GENERATED->value => [
                TournamentStatus::ONGOING->value,
                TournamentStatus::CHECKIN_CLOSED->value,
                TournamentStatus::CANCELLED->value,
            ],
            TournamentStatus::ONGOING->value => [
                TournamentStatus::COMPLETED->value,
                // NOTE: cancellation forbidden on ONGOING per spec
            ],
            // ...
        ];
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    /**
     * Ensure the tournament has the minimum config to open registration.
     *
     * @throws LogicException
     */
    public function guardCanPublish(Tournament $tournament): void
    {
        if (empty($tournament->name)) {
            throw new LogicException('Tournament must have a name before publishing.');
        }

        if (($tournament->max_participants ?? 0) < 2) {
            throw new LogicException('Tournament max_participants must be at least 2.');
        }

        if ($tournament->registration_open_at === null) {
            throw new LogicException('Tournament registration_open_at must be set.');
        }

        if ($tournament->registration_close_at === null) {
            throw new LogicException('Tournament registration_close_at must be set.');
        }
    }

    /**
     * Ensure minimum participants are checked in before generating bracket.
     *
     * @throws LogicException
     */
    public function guardCanCloseCheckin(Tournament $tournament): void
    {
        $checkedInCount = $tournament->checkins()->count();

        if ($checkedInCount < ($tournament->min_participants ?? 2)) {
            throw new LogicException(
                "Not enough participants checked in: {$checkedInCount} checked in, "
                ."minimum {$tournament->min_participants} required."
            );
        }
    }

    /**
     * Ensure minimum participants are checked in before generating bracket.
     *
     * @throws LogicException
     */
    public function guardCanGenerateBracket(Tournament $tournament): void
    {
        $participantCount = $tournament->participants()->count();

        if ($participantCount < ($tournament->min_participants ?? 2)) {
            throw new LogicException(
                "Not enough participants to start: {$participantCount} checked in, "
                ."minimum {$tournament->min_participants} required."
            );
        }
    }

    /**
     * Ensure a bracket has been generated before starting the tournament.
     *
     * @throws LogicException
     */
    public function guardCanStart(Tournament $tournament): void
    {
        if (! $tournament->brackets()->exists()) {
            throw new LogicException('Bracket must be generated before starting the tournament.');
        }
    }

    // -------------------------------------------------------------------------
    // Transition
    // -------------------------------------------------------------------------

    /**
     * Transition the tournament to a new status.
     *
     * @throws InvalidStateTransitionException
     * @throws LogicException
     */
    public function transition(Tournament $tournament, TournamentStatus $to, array $context = []): void
    {
        $this->assertValidTransition($tournament->status, $to);

        // Apply guards for guarded transitions
        if ($tournament->status === TournamentStatus::DRAFT && $to === TournamentStatus::PUBLISHED) {
            $this->guardCanPublish($tournament);
        }

        if ($tournament->status === TournamentStatus::CHECKIN_OPEN && $to === TournamentStatus::CHECKIN_CLOSED) {
            $this->guardCanCloseCheckin($tournament);
        }

        if ($tournament->status === TournamentStatus::CHECKIN_CLOSED && $to === TournamentStatus::BRACKET_GENERATED) {
            $this->guardCanGenerateBracket($tournament);
        }

        if ($tournament->status === TournamentStatus::BRACKET_GENERATED && $to === TournamentStatus::ONGOING) {
            $this->guardCanStart($tournament);
        }

        // Stamp timestamps
        $now = now();

        match ($to) {
            TournamentStatus::REGISTRATION_OPEN => $tournament->registration_open_at ??= $now,
            TournamentStatus::REGISTRATION_CLOSED => $tournament->registration_close_at ??= $now,
            TournamentStatus::CHECKIN_OPEN => $tournament->checkin_open_at ??= $now,
            TournamentStatus::CHECKIN_CLOSED => $tournament->checkin_close_at ??= $now,
            TournamentStatus::ONGOING => $tournament->start_at ??= $now,
            TournamentStatus::COMPLETED => $tournament->completed_at = $now,
            TournamentStatus::CANCELLED => $tournament->cancelled_at = $now,
            default => null,
        };

        $tournament->status = $to;
        $tournament->save();

        activity()
            ->performedOn($tournament)
            ->causedBy(Auth::user())
            ->withProperties(array_merge($context, ['to' => $to->value]))
            ->log("Tournament status changed to {$to->value}");
    }
}
