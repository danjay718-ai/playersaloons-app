<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;

class CompleteTournamentAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Mark a tournament as completed (ONGOING → COMPLETED).
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(Tournament $tournament): Tournament
    {
        $this->stateMachine->transition($tournament, TournamentStatus::COMPLETED);

        TournamentCompleted::dispatch((int) $tournament->getKey(), $tournament->name);

        return $tournament->fresh() ?? $tournament;
    }
}
