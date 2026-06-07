<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentRegistrationOpened;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;

class OpenRegistrationAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Open registration for a published tournament (PUBLISHED → REGISTRATION_OPEN).
     *
     * @throws \App\Shared\Exceptions\InvalidStateTransitionException
     */
    public function execute(Tournament $tournament): Tournament
    {
        $this->stateMachine->transition($tournament, TournamentStatus::REGISTRATION_OPEN);

        TournamentRegistrationOpened::dispatch((int) $tournament->getKey());

        return $tournament->fresh() ?? $tournament;
    }
}
