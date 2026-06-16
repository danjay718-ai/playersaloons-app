<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchResultSubmitted;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchResultSubmission;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitMatchResultAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Submit match result (in_progress -> waiting_for_confirmation).
     */
    public function execute(
        GameMatch $match,
        int $submittedByUserId,
        int $winnerRegistrationId,
        ?string $notes = null,
        ?UploadedFile $proofFile = null
    ): MatchResultSubmission {
        return DB::transaction(function () use ($match, $submittedByUserId, $winnerRegistrationId, $notes, $proofFile): MatchResultSubmission {
            if ($winnerRegistrationId !== $match->player_a_registration_id && $winnerRegistrationId !== $match->player_b_registration_id) {
                throw new InvalidArgumentException('Winner must be one of the match participants.');
            }

            // Transition to WAITING_FOR_CONFIRMATION
            $this->stateMachine->transition($match, MatchStatus::WAITING_FOR_CONFIRMATION);

            $proofPath = null;
            if ($proofFile && $proofFile->isValid()) {
                $proofPath = $proofFile->store("matches/{$match->id}/submissions", 'public');
            }

            $submission = MatchResultSubmission::query()->create([
                'match_id' => $match->id,
                'submitted_by' => $submittedByUserId,
                'winner_registration_id' => $winnerRegistrationId,
                'notes' => $notes,
                'proof_path' => $proofPath,
                'submitted_at' => Carbon::now(),
            ]);

            $match->result_submitted_at = Carbon::now();
            $match->save();

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
