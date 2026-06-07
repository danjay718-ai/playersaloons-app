<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchResultSubmission;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use App\Modules\Match\Events\MatchResultSubmitted;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitMatchResultAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Submit match result (in_progress -> result_submitted).
     */
    public function execute(
        GameMatch $match,
        int $submittedByUserId,
        int $winnerRegistrationId,
        ?string $notes = null
    ): MatchResultSubmission {
        return DB::transaction(function () use ($match, $submittedByUserId, $winnerRegistrationId, $notes): MatchResultSubmission {
            if ($winnerRegistrationId !== $match->player_a_registration_id && $winnerRegistrationId !== $match->player_b_registration_id) {
                throw new InvalidArgumentException('Winner must be one of the match participants.');
            }

            // Transition state first (will check validity of IN_PROGRESS -> RESULT_SUBMITTED)
            $this->stateMachine->transition($match, MatchStatus::RESULT_SUBMITTED);

            $submission = MatchResultSubmission::query()->create([
                'match_id' => $match->id,
                'submitted_by' => $submittedByUserId,
                'winner_registration_id' => $winnerRegistrationId,
                'notes' => $notes,
                'submitted_at' => \Illuminate\Support\Carbon::now(),
            ]);

            MatchResultSubmitted::dispatch(
                $match->id,
                $submission->id,
                $submittedByUserId,
                $winnerRegistrationId
            );

            return $submission;
        });
    }
}
