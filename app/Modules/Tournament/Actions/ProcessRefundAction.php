<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Events\TournamentRefunded;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;

class ProcessRefundAction
{
    public function __construct(private readonly TournamentStateMachine $stateMachine) {}

    /**
     * Mark the tournament as fully refunded (CANCELLED/COMPLETED → REFUNDED).
     *
     * @throws InvalidStateTransitionException
     */
    public function execute(Tournament $tournament): Tournament
    {
        $this->stateMachine->transition($tournament, TournamentStatus::REFUNDED);

        TournamentRefunded::dispatch((int) $tournament->getKey());

        return $tournament->fresh() ?? $tournament;
    }
}
