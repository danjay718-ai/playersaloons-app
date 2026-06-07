<?php

declare(strict_types=1);

namespace App\Modules\Tournament\StateMachines;

use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\SeatReservationStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\StateMachines\AbstractStateMachine;

class SeatReservationStateMachine extends AbstractStateMachine
{
    /**
     * {@inheritDoc}
     *
     * Note: TournamentRegistration has no dedicated seat_status column.
     * This machine operates on the model's `status` field,
     * treating it as a SeatReservationStatus value.
     */
    protected function transitions(): array
    {
        return [
            SeatReservationStatus::RESERVED->value => [
                SeatReservationStatus::CONFIRMED->value,
                SeatReservationStatus::EXPIRED->value,
                SeatReservationStatus::CANCELLED->value,
            ],
            SeatReservationStatus::CONFIRMED->value => [],
            SeatReservationStatus::EXPIRED->value => [],
            SeatReservationStatus::CANCELLED->value => [],
        ];
    }

    /**
     * Transition a tournament registration's seat status.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(TournamentRegistration $registration, SeatReservationStatus $to): void
    {
        $this->assertValidTransition($registration->status, $to);

        $registration->status = $to;
        $registration->save();
    }
}
