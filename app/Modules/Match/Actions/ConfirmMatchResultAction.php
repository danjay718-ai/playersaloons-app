<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

class ConfirmMatchResultAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Confirm a match result.
     */
    public function execute(GameMatch $match, int $userId): void
    {
        DB::transaction(function () use ($match, $userId) {
            // 1. Authorization: User must be the opponent (not the submitter)
            $latestSubmission = $match->resultSubmissions()->latest('submitted_at')->first();

            if (! $latestSubmission) {
                throw new LogicException('No submitted result was found for this match.');
            }
            $submitterId = $latestSubmission?->submitted_by;

            if ($userId === $submitterId) {
                throw new LogicException('You cannot confirm your own submission.');
            }

            $confirmerSide = $match->playerARegistration?->includesUser($userId)
                ? $match->playerARegistration
                : ($match->playerBRegistration?->includesUser($userId) ? $match->playerBRegistration : null);
            $submitterSide = $match->playerARegistration?->includesUser((int) $submitterId)
                ? $match->playerARegistration
                : ($match->playerBRegistration?->includesUser((int) $submitterId) ? $match->playerBRegistration : null);
            if ($confirmerSide === null) {
                throw new LogicException('You are not authorized to confirm this match.');
            }
            if ($submitterSide?->id === $confirmerSide->id) {
                throw new LogicException('A teammate cannot confirm your side’s submission.');
            }

            // 2. Status validation
            if ($match->status !== MatchStatus::WAITING_FOR_CONFIRMATION) {
                throw new LogicException('Match is not awaiting confirmation.');
            }

            // 3. Set the winner from the latest submission
            $winnerRegistrationId = (int) $latestSubmission->winner_registration_id;
            $match->winner_registration_id = $winnerRegistrationId;
            $match->save();

            // 4. Transition to COMPLETED
            $this->stateMachine->transition($match, MatchStatus::COMPLETED);

            // 5. Dispatch completion event for further processing (bracket progression, etc.)
            MatchCompleted::dispatch(
                (int) $match->id,
                (int) $match->tournament_id,
                $winnerRegistrationId,
            );
        });
    }
}
