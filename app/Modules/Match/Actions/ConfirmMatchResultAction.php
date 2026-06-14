<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Events\MatchCompleted;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

class ConfirmMatchResultAction
{
    public function __construct(private readonly \App\Modules\Match\StateMachines\MatchStateMachine $stateMachine) {}

    /**
     * Confirm a match result.
     */
    public function execute(GameMatch $match, int $userId): void
    {
        DB::transaction(function () use ($match, $userId) {
            // 1. Authorization: User must be the opponent (not the submitter)
            $submitterId = $match->resultSubmissions()->latest()->first()?->submitted_by;
            
            if ($userId === $submitterId) {
                throw new LogicException('You cannot confirm your own submission.');
            }
            
            if (($match->playerARegistration?->user_id !== $userId) && ($match->playerBRegistration?->user_id !== $userId)) {
                throw new LogicException('You are not authorized to confirm this match.');
            }

            // 2. Status validation
            if ($match->status !== MatchStatus::WAITING_FOR_CONFIRMATION) {
                throw new LogicException('Match is not awaiting confirmation.');
            }

            // 3. Transition to COMPLETED
            $this->stateMachine->transition($match, MatchStatus::COMPLETED);
            
            // Dispatch completion event for further processing (bracket progression, etc.)
            MatchCompleted::dispatch($match);
        });
    }
}
