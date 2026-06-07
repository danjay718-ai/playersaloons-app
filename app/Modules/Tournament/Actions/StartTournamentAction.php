<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentStarted;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;

class StartTournamentAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Start a tournament (BRACKET_GENERATED → ONGOING).
     *
     * Validates that a bracket exists via the state machine guard.
     *
     * @throws \App\Shared\Exceptions\InvalidStateTransitionException
     * @throws \LogicException
     */
    public function execute(Tournament $tournament): Tournament
    {
        $this->stateMachine->transition($tournament, TournamentStatus::ONGOING);

        TournamentStarted::dispatch((int) $tournament->getKey(), $tournament->name);

        return $tournament->fresh() ?? $tournament;
    }
}
