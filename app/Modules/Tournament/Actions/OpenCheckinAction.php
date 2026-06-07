<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentCheckinOpened;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;

class OpenCheckinAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Open check-in for a tournament (REGISTRATION_CLOSED → CHECKIN_OPEN).
     *
     * @throws \App\Shared\Exceptions\InvalidStateTransitionException
     */
    public function execute(Tournament $tournament): Tournament
    {
        $this->stateMachine->transition($tournament, TournamentStatus::CHECKIN_OPEN);

        TournamentCheckinOpened::dispatch((int) $tournament->getKey(), $tournament->name);

        return $tournament->fresh() ?? $tournament;
    }
}
