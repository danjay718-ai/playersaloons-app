<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentPublished;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;

class PublishTournamentAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Publish a tournament (DRAFT → PUBLISHED).
     *
     * Validates that the tournament has all required config via the state machine guard.
     *
     * @throws InvalidStateTransitionException
     * @throws \LogicException
     */
    public function execute(Tournament $tournament): Tournament
    {
        $this->stateMachine->transition($tournament, TournamentStatus::PUBLISHED);

        TournamentPublished::dispatch((int) $tournament->getKey());

        return $tournament->fresh() ?? $tournament;
    }
}
