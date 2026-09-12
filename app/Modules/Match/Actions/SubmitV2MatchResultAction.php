<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\BroadcastMatchResultSubmitted;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Events\MatchResultSubmitted;
use App\Modules\Match\Jobs\ResolveV2ResultTimeoutJob;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchAttempt;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Models\MatchResultSubmission;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchOutcome;
use App\Shared\Enums\MatchStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class SubmitV2MatchResultAction
{
    public function execute(
        GameMatch $match,
        int $userId,
        MatchOutcome $outcome,
        ?string $notes = null,
        ?UploadedFile $proof = null,
    ): MatchResultSubmission {
        $timeoutAttemptId = null;
        $resultDeadline = null;
        $matchUuid = (string) $match->uuid;

        $submission = DB::transaction(function () use ($match, $userId, $outcome, $notes, $proof, &$timeoutAttemptId, &$resultDeadline, &$matchUuid): MatchResultSubmission {
            $tournamentId = GameMatch::query()->whereKey($match->id)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $locked = GameMatch::query()
                ->with(['tournament', 'playerARegistration', 'playerBRegistration'])
                ->lockForUpdate()
                ->findOrFail($match->id);
            $matchUuid = (string) $locked->uuid;

            if ((int) $locked->tournament->workflow_version !== 2) {
                throw new LogicException('This result workflow is only available for V2 tournaments.');
            }
            if (! in_array($locked->status, [MatchStatus::IN_PROGRESS, MatchStatus::WAITING_FOR_CONFIRMATION], true)) {
                throw new LogicException('Results can only be submitted for an active match.');
            }

            $registration = $this->participantRegistration($locked, $userId);
            $attempt = MatchAttempt::query()->firstOrCreate(
                ['match_id' => $locked->id, 'attempt_number' => $locked->active_attempt_number],
                ['uuid' => Str::uuid()->toString(), 'status' => 'open'],
            );
            $attempt = MatchAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== 'open') {
                throw new LogicException('This match attempt has already been resolved.');
            }
            if ($attempt->result_deadline_at !== null && now()->greaterThanOrEqualTo($attempt->result_deadline_at)) {
                throw new LogicException('The result submission deadline has passed.');
            }
            if ($attempt->submissions()->where('registration_id', $registration->id)->exists()) {
                throw new LogicException('Your result for this attempt has already been submitted.');
            }

            $proofPath = $proof?->isValid()
                ? $proof->store("matches/{$locked->id}/attempts/{$attempt->attempt_number}", 'public')
                : null;
            $submission = MatchResultSubmission::query()->create([
                'match_id' => $locked->id,
                'match_attempt_id' => $attempt->id,
                'submitted_by' => $userId,
                'registration_id' => $registration->id,
                'winner_registration_id' => $outcome === MatchOutcome::WIN
                    ? $registration->id
                    : ($outcome === MatchOutcome::LOSS ? $this->opponentRegistrationId($locked, $registration->id) : null),
                'outcome' => $outcome->value,
                'notes' => $notes,
                'proof_path' => $proofPath,
                'submitted_at' => now(),
            ]);

            if ($attempt->first_submitted_at === null) {
                $minutes = max(1, (int) ($locked->tournament->waiting_result_time ?: 5));
                $resultDeadline = now()->addMinutes($minutes);
                $attempt->update([
                    'first_submitted_at' => now(),
                    'result_deadline_at' => $resultDeadline,
                ]);
                $timeoutAttemptId = (int) $attempt->id;
                $locked->forceFill([
                    'status' => MatchStatus::WAITING_FOR_CONFIRMATION,
                    'result_submitted_at' => now(),
                ])->save();
            } else {
                $resultDeadline = $attempt->result_deadline_at;
            }

            MatchResultSubmitted::dispatch($locked->id, $submission->id, $userId, $submission->winner_registration_id);

            $submissions = $attempt->submissions()->orderBy('id')->get();
            if ($submissions->count() === 2) {
                $this->resolvePair($locked, $attempt, $submissions[0], $submissions[1]);
            }

            return $submission;
        }, 3);

        if ($timeoutAttemptId !== null && $resultDeadline !== null) {
            ResolveV2ResultTimeoutJob::dispatch($timeoutAttemptId)->delay($resultDeadline);
        }

        broadcast(new BroadcastMatchResultSubmitted(
            $matchUuid,
            $resultDeadline?->toIso8601String(),
        ))->toOthers();

        return $submission;
    }

    private function participantRegistration(GameMatch $match, int $userId): TournamentRegistration
    {
        foreach ([$match->playerARegistration, $match->playerBRegistration] as $registration) {
            if ($registration?->includesUser($userId)) {
                return $registration;
            }
        }

        throw new InvalidArgumentException('Only match participants can submit a result.');
    }

    private function opponentRegistrationId(GameMatch $match, int $registrationId): int
    {
        return (int) ($registrationId === (int) $match->player_a_registration_id
            ? $match->player_b_registration_id
            : $match->player_a_registration_id);
    }

    private function resolvePair(
        GameMatch $match,
        MatchAttempt $attempt,
        MatchResultSubmission $first,
        MatchResultSubmission $second,
    ): void {
        $a = MatchOutcome::from($first->outcome);
        $b = MatchOutcome::from($second->outcome);

        if ($a === MatchOutcome::DRAW && $b === MatchOutcome::DRAW) {
            $attempt->update(['status' => 'rematch', 'resolution' => 'draw', 'resolved_at' => now()]);
            $stalledDeadline = $match->stalled_deadline_at !== null ? now()->addMinutes(30) : null;
            $match->forceFill([
                'status' => MatchStatus::IN_PROGRESS,
                'active_attempt_number' => $attempt->attempt_number + 1,
                'result_submitted_at' => null,
                'resolution_reason' => 'rematch',
                'stalled_deadline_at' => $stalledDeadline,
                'final_resolution_eligible_at' => null,
                'final_resolution_notified_at' => null,
            ])->save();
            if ($stalledDeadline !== null) {
                MatchAttempt::query()->create([
                    'uuid' => Str::uuid()->toString(), 'match_id' => $match->id,
                    'attempt_number' => $attempt->attempt_number + 1, 'status' => 'open',
                    'stalled_deadline_at' => $stalledDeadline,
                ]);
            }
            MatchRematchCreated::dispatch($match->id, $match->id);

            return;
        }

        $consistent = ($a === MatchOutcome::WIN && $b === MatchOutcome::LOSS)
            || ($a === MatchOutcome::LOSS && $b === MatchOutcome::WIN);
        if (! $consistent) {
            $attempt->update(['status' => 'disputed', 'resolution' => 'conflict', 'resolved_at' => now()]);
            $match->forceFill(['status' => MatchStatus::DISPUTED, 'resolution_reason' => 'conflicting_submissions'])->save();
            $dispute = MatchDispute::query()->create([
                'uuid' => Str::uuid()->toString(),
                'match_id' => $match->id,
                'opened_by' => $first->submitted_by,
                'status' => DisputeStatus::OPEN,
                'reason' => 'V2 result submissions conflict.',
            ]);
            MatchDisputed::dispatch($match->id, $dispute->id, $first->submitted_by);

            return;
        }

        $winner = $a === MatchOutcome::WIN ? $first->registration_id : $second->registration_id;
        $this->complete($match, $attempt, (int) $winner, 'confirmed_submissions');
    }

    public function complete(GameMatch $match, MatchAttempt $attempt, int $winnerRegistrationId, string $reason): void
    {
        $attempt->update(['status' => 'resolved', 'resolution' => $reason, 'resolved_at' => now()]);
        $match->forceFill([
            'winner_registration_id' => $winnerRegistrationId,
            'status' => MatchStatus::COMPLETED,
            'completed_at' => now(),
            'stalled_deadline_at' => null,
            'round_deadline_at' => null,
            'final_resolution_eligible_at' => null,
            'resolution_reason' => $reason,
        ])->save();
        MatchCompleted::dispatch($match->id, $match->tournament_id, $winnerRegistrationId);
    }
}
