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
use LogicException;

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
        $submission = DB::transaction(function () use ($match, $submittedByUserId, $winnerRegistrationId, $notes, $proofFile): MatchResultSubmission {
            /** @var GameMatch $lockedMatch */
            $lockedMatch = GameMatch::query()
                ->with(['playerARegistration', 'playerBRegistration'])
                ->lockForUpdate()
                ->findOrFail($match->getKey());

            if ($lockedMatch->status !== MatchStatus::IN_PROGRESS) {
                throw new LogicException('Results can be submitted once the match is in progress.');
            }

            if (! $lockedMatch->playerARegistration?->includesUser($submittedByUserId) && ! $lockedMatch->playerBRegistration?->includesUser($submittedByUserId)) {
                throw new InvalidArgumentException('Only match participants can submit a result.');
            }
            if ($winnerRegistrationId !== $lockedMatch->player_a_registration_id && $winnerRegistrationId !== $lockedMatch->player_b_registration_id) {
                throw new InvalidArgumentException('Winner must be one of the match participants.');
            }

            // Transition to WAITING_FOR_CONFIRMATION
            $this->stateMachine->transition($lockedMatch, MatchStatus::WAITING_FOR_CONFIRMATION);

            $proofPath = null;
            if ($proofFile && $proofFile->isValid()) {
                $proofPath = $proofFile->store("matches/{$lockedMatch->id}/submissions", 'public');
            }

            $submission = MatchResultSubmission::query()->create([
                'match_id' => $lockedMatch->id,
                'submitted_by' => $submittedByUserId,
                'winner_registration_id' => $winnerRegistrationId,
                'notes' => $notes,
                'proof_path' => $proofPath,
                'submitted_at' => Carbon::now(),
            ]);

            $lockedMatch->result_submitted_at = Carbon::now();
            $lockedMatch->save();

            MatchResultSubmitted::dispatch(
                $lockedMatch->id,
                $submission->id,
                $submittedByUserId,
                $winnerRegistrationId
            );

            return $submission;
        });

        // Preserve the expected in-memory state for callers that continue to
        // use the match instance immediately after submitting a result.
        $match->refresh();

        return $submission;
    }
}
