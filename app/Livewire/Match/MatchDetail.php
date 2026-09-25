<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use App\Livewire\Concerns\HandlesUserFacingErrors;
use App\Modules\Identity\Models\PlayerDisputeStrike;
use App\Modules\Identity\Models\PlayerExperienceAward;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\AutoForfeitAction;
use App\Modules\Match\Actions\ConfirmMatchResultAction;
use App\Modules\Match\Actions\OpenDisputeAction;
use App\Modules\Match\Actions\ResolveV2ResultTimeoutAction;
use App\Modules\Match\Actions\SubmitEvidenceAction;
use App\Modules\Match\Actions\SubmitMatchResultAction;
use App\Modules\Match\Actions\SubmitV2MatchResultAction;
use App\Modules\Match\Actions\VoteForRematchAction;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Services\MatchReadinessService;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchOutcome;
use App\Shared\Enums\MatchStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LogicException;

class MatchDetail extends Component
{
    use HandlesUserFacingErrors;
    use WithFileUploads;

    public string $uuid;

    public bool $embedded = false;

    public ?int $winnerRegistrationId = null;

    public string $resultOutcome = '';

    public string $notes = '';

    public string $disputeReason = '';

    public ?TemporaryUploadedFile $evidenceFile = null;

    public ?TemporaryUploadedFile $submissionProof = null;

    public string $lobbyCode = '';

    public string $lobbyPassword = '';

    public string $serverRegion = '';

    public string $lobbyInstructions = '';

    public function confirmResult(ConfirmMatchResultAction $action)
    {
        $match = GameMatch::query()
            ->where('uuid', $this->uuid)
            ->with(['playerARegistration', 'playerBRegistration', 'resultSubmissions'])
            ->firstOrFail();

        if (! Auth::check()) {
            session()->flash('error', 'You must be logged in to confirm a result.');

            return;
        }

        try {
            $action->execute($match, (int) Auth::id());
            session()->flash('message', 'Match result confirmed! The match is now complete.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to confirm the match result.'));
        }
    }

    public function adminCompleteMatch(int $winnerId)
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
            return;
        }

        $match = GameMatch::query()->where('uuid', $this->uuid)->firstOrFail();

        try {
            DB::transaction(function () use ($match, $winnerId) {
                $match->winner_registration_id = $winnerId;
                $match->status = MatchStatus::COMPLETED;
                $match->completed_at = now();
                $match->save();

                MatchCompleted::dispatch(
                    (int) $match->id,
                    (int) $match->tournament_id,
                    (int) $match->winner_registration_id
                );
            });
            session()->flash('message', 'Match finalized by administrator.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to finalize the match result.'));
        }
    }

    public function mount(string $uuid, bool $embedded = false): void
    {
        $this->uuid = $uuid;
        $this->embedded = $embedded;
        $match = GameMatch::query()->where('uuid', $uuid)->with('tournament:id,uuid,workflow_version')->firstOrFail([
            'id', 'uuid', 'tournament_id', 'lobby_code', 'lobby_password', 'server_region', 'lobby_instructions',
        ]);
        abort_if((int) $match->tournament->workflow_version === 2 && ! config('features.tournament_v2.enabled'), 404);
        if (! $this->embedded && (int) $match->tournament->workflow_version === 2) {
            $tournamentUrl = route('tournaments.view', ['uuid' => $match->tournament->uuid])
                .'?'.http_build_query([
                    'activeTab' => 'submit-results',
                    'match' => $match->uuid,
                ]);
            $this->redirect($tournamentUrl, navigate: true);

            return;
        }
        $this->lobbyCode = (string) ($match->lobby_code ?? '');
        $this->lobbyPassword = (string) ($match->lobby_password ?? '');
        $this->serverRegion = (string) ($match->server_region ?? '');
        $this->lobbyInstructions = (string) ($match->lobby_instructions ?? '');
    }

    public function markReady(MatchReadinessService $readiness): void
    {
        if (! Auth::check()) {
            return;
        }

        try {
            $match = GameMatch::query()->where('uuid', $this->uuid)->firstOrFail();
            $readiness->markReady($match, (int) Auth::id());
            session()->flash('message', 'You are ready. We will notify you when the match starts.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to update your ready status.'));
        }
    }

    public function refreshMatchStatus(MatchReadinessService $readiness): void
    {
        if (! Auth::check()) {
            return;
        }

        $match = GameMatch::query()
            ->where('uuid', $this->uuid)
            ->with(['playerARegistration', 'playerBRegistration'])
            ->first();

        if ($match === null || (
            ! $match->playerARegistration?->includesUser((int) Auth::id())
            && ! $match->playerBRegistration?->includesUser((int) Auth::id())
        )) {
            return;
        }

        if ($match->status === MatchStatus::READY) {
            $readiness->reconcile($match);
        }
    }

    public function reportOpponentNotHere(MatchReadinessService $readiness): void
    {
        if (! Auth::check()) {
            return;
        }

        try {
            $match = GameMatch::query()->where('uuid', $this->uuid)->firstOrFail();
            $readiness->reportOpponentAbsent($match, (int) Auth::id());
            session()->flash('message', 'Extra Wait Time started. Your opponent has been notified.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to report the absent opponent.'));
        }
    }

    public function saveMatchRoomDetails(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user?->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
            abort(403);
        }

        $data = $this->validate([
            'lobbyCode' => 'nullable|string|max:191',
            'lobbyPassword' => 'nullable|string|max:191',
            'serverRegion' => 'nullable|string|max:64',
            'lobbyInstructions' => 'nullable|string|max:2000',
        ]);

        $match = GameMatch::query()->where('uuid', $this->uuid)->firstOrFail();
        $match->update([
            'lobby_code' => trim($data['lobbyCode']) ?: null,
            'lobby_password' => trim($data['lobbyPassword']) ?: null,
            'server_region' => trim($data['serverRegion']) ?: null,
            'lobby_instructions' => trim($data['lobbyInstructions']) ?: null,
        ]);
        session()->flash('message', 'Match Room details saved.');
    }

    public function submitResult(SubmitMatchResultAction $action, SubmitV2MatchResultAction $v2Action)
    {
        $match = GameMatch::query()
            ->where('uuid', $this->uuid)
            ->with(['playerARegistration', 'playerBRegistration', 'tournament'])
            ->firstOrFail();

        /** @var User $user */
        $user = Auth::user();
        if (! Auth::check() || ! $user->can('submitResult', $match)) {
            session()->flash('error', 'You are not authorized to submit results for this match.');

            return;
        }

        if ((int) $match->tournament->workflow_version === 2) {
            $this->validate([
                'resultOutcome' => ['required', 'in:win,loss,draw'],
                'notes' => ['nullable', 'string', 'max:500'],
                'submissionProof' => ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
            ]);
            try {
                $v2Action->execute(
                    $match,
                    (int) Auth::id(),
                    MatchOutcome::from($this->resultOutcome),
                    $this->notes,
                    $this->submissionProof,
                );
                $updatedMatch = $match->fresh();
                $responseMinutes = max(1, (int) ($updatedMatch->tournament->waiting_result_time ?: 5));
                session()->flash('message', match ($updatedMatch->status) {
                    MatchStatus::DISPUTED => 'Conflicting results were reported. Submit your dispute reason and proof for admin review.',
                    MatchStatus::COMPLETED => 'Both results agree. The match is complete.',
                    default => "Result submitted. Your opponent has {$responseMinutes} minutes from the first submission to respond.",
                });
                $this->reset(['resultOutcome', 'notes', 'submissionProof']);
            } catch (\Exception $e) {
                session()->flash('error', $this->safeError($e, 'Unable to submit the match result.'));
            }

            return;
        }

        if ($match->status !== MatchStatus::IN_PROGRESS) {
            session()->flash('error', 'This match is still in the ready-up phase. Results can be submitted once the match is in progress.');

            return;
        }

        $this->validate([
            'winnerRegistrationId' => ['required', 'integer', 'in:'.$match->player_a_registration_id.','.$match->player_b_registration_id],
            'notes' => ['nullable', 'string', 'max:500'],
            'submissionProof' => ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
        ]);

        try {
            $action->execute(
                $match,
                (int) Auth::id(),
                (int) $this->winnerRegistrationId,
                $this->notes,
                $this->submissionProof
            );
            session()->flash('message', 'Result submitted successfully!');
            $this->reset(['winnerRegistrationId', 'notes', 'submissionProof']);
        } catch (InvalidArgumentException|InvalidStateTransitionException|LogicException $e) {
            session()->flash('error', $e->getMessage());
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to submit the match result.'));
        }
    }

    public function openDispute(OpenDisputeAction $action, SubmitEvidenceAction $evidenceAction)
    {
        $match = GameMatch::query()->where('uuid', $this->uuid)->firstOrFail();

        /** @var User $user */
        $user = Auth::user();
        if (! Auth::check() || ! $user->can('dispute', $match)) {
            session()->flash('error', 'You are not authorized to open a dispute for this match.');

            return;
        }

        $this->validate([
            'disputeReason' => 'nullable|string|max:1000',
            'evidenceFile' => ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
        ]);

        try {
            $dispute = $action->execute($match, (int) Auth::id(), $this->disputeReason);

            if ($this->evidenceFile) {
                $evidenceAction->execute($dispute, (int) Auth::id(), $this->evidenceFile);
            }

            session()->flash('message', $this->evidenceFile
                ? 'Dispute and proof submitted successfully.'
                : 'Dispute opened successfully. You may add proof below.');
            $this->reset(['disputeReason', 'evidenceFile']);
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to open the match dispute.'));
        }
    }

    public function voteForRematch(VoteForRematchAction $action): void
    {
        if (! Auth::check()) {
            session()->flash('error', 'You must be logged in to request a rematch.');

            return;
        }
        $match = GameMatch::query()->where('uuid', $this->uuid)->firstOrFail();
        try {
            $rematch = $action->execute($match, (int) Auth::id());
            session()->flash('message', $rematch ? 'Rematch agreed! A new match is ready.' : 'Rematch requested. Waiting for your opponent to agree.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to request a rematch.'));
        }
    }

    public function submitEvidence(SubmitEvidenceAction $action)
    {
        $match = GameMatch::query()->where('uuid', $this->uuid)->firstOrFail();
        $dispute = $match->disputes()->where('status', '!=', DisputeStatus::RESOLVED->value)->first();

        if (! $dispute) {
            session()->flash('error', 'No active dispute found for this match.');

            return;
        }

        if (! Auth::check()) {
            session()->flash('error', 'You are not authorized to submit evidence.');

            return;
        }

        if (! $match->playerARegistration?->includesUser((int) Auth::id()) && ! $match->playerBRegistration?->includesUser((int) Auth::id())) {
            session()->flash('error', 'You are not authorized to submit evidence.');

            return;
        }

        $this->validate([
            'evidenceFile' => ['required', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
        ]);

        try {
            $action->execute($dispute, (int) Auth::id(), $this->evidenceFile);
            session()->flash('message', 'Evidence uploaded successfully! The tournament admins will review it.');
            $this->reset('evidenceFile');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to upload match evidence.'));
        }
    }

    public function submitDisputeStatement(SubmitEvidenceAction $action): void
    {
        if (! Auth::check()) {
            session()->flash('error', 'You must be logged in to submit dispute details.');

            return;
        }

        $match = GameMatch::query()
            ->where('uuid', $this->uuid)
            ->with(['playerARegistration', 'playerBRegistration'])
            ->firstOrFail();

        if ($match->status !== MatchStatus::DISPUTED || $match->resolution_reason !== 'conflicting_submissions') {
            session()->flash('error', 'There is no result conflict to explain.');

            return;
        }

        $dispute = $match->disputes()->where('status', '!=', DisputeStatus::RESOLVED->value)->first();
        if ($dispute === null) {
            session()->flash('error', 'There is no active dispute for this match.');

            return;
        }

        $this->validate([
            'disputeReason' => ['required', 'string', 'min:10', 'max:2000'],
            'evidenceFile' => ['required', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp'],
        ]);

        try {
            $action->execute($dispute, (int) Auth::id(), $this->evidenceFile, $this->disputeReason);
            $this->reset(['disputeReason', 'evidenceFile']);
            session()->flash('message', 'Your dispute reason and proof were submitted for admin review.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to submit your dispute details.'));
        }
    }

    public function render()
    {
        $match = GameMatch::query()
            ->where('uuid', $this->uuid)
            ->with([
                'round',
                'playerARegistration.user.profile',
                'playerARegistration.team',
                'playerARegistration.platform',
                'playerARegistration.rosterMembers',
                'playerARegistration.tournamentTeam.members.user:id,username',
                'playerBRegistration.user.profile',
                'playerBRegistration.team',
                'playerBRegistration.platform',
                'playerBRegistration.rosterMembers',
                'playerBRegistration.tournamentTeam.members.user:id,username',
                'tournament.game.translations',
                'tournament.platform',
                'winnerRegistration.user.profile',
                'resultSubmissions' => fn ($query) => $query
                    ->with('user:id,username')
                    ->orderByDesc('submitted_at'),
                'attempts.submissions',
                'disputes' => function ($q) {
                    $q->with('evidence.uploadedBy:id,username');
                },
                'rematchVotes',
            ])
            ->firstOrFail();

        // JIT Timeout Check
        if ((int) $match->tournament->workflow_version !== 2 && $match->isTimedOut()) {
            app(AutoForfeitAction::class)->execute($match);
            $match->refresh();
        }

        // The queued timeout remains the primary resolver, but an expired V2
        // response window must also self-heal when either participant returns.
        // This prevents a match from staying stuck if a worker was unavailable.
        if ((int) $match->tournament->workflow_version === 2
            && $match->status === MatchStatus::WAITING_FOR_CONFIRMATION) {
            $timeoutAttempt = $match->attempts->firstWhere('attempt_number', $match->active_attempt_number);
            if ($timeoutAttempt?->result_deadline_at?->isPast()
                && app(ResolveV2ResultTimeoutAction::class)->execute((int) $timeoutAttempt->id)) {
                $match->refresh();
            }
        }

        /** @var User|null $user */
        $user = Auth::user();
        $isParticipant = $user && (
            $match->playerARegistration?->includesUser($user->id)
            || $match->playerBRegistration?->includesUser($user->id)
        );
        $viewerRegistration = $user && $match->playerARegistration?->includesUser($user->id)
            ? $match->playerARegistration
            : ($user && $match->playerBRegistration?->includesUser($user->id) ? $match->playerBRegistration : null);
        $isDefeated = $viewerRegistration !== null
            && in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)
            && $match->winner_registration_id !== null
            && (int) $match->winner_registration_id !== (int) $viewerRegistration->id;
        $defeatXp = $isDefeated
            ? (int) PlayerExperienceAward::query()
                ->where('user_id', $user->id)
                ->where('source_type', 'tournament')
                ->where('source_id', $match->tournament_id)
                ->sum('amount')
            : 0;
        $defeatMessage = null;
        if ($isDefeated && $viewerRegistration !== null) {
            $responseMinutes = max(1, (int) ($match->tournament->waiting_result_time ?: 5));
            $hasDishonestyStrike = PlayerDisputeStrike::query()
                ->where('match_id', $match->id)
                ->where('user_id', $user->id)
                ->exists();
            $defeatMessage = match (true) {
                $match->resolution_reason === 'confirmed_submissions' => 'You lost because both submitted results confirmed your opponent as the winner.',
                $match->resolution_reason === 'opponent_submission_timeout' => "You lost because you did not report your result within {$responseMinutes} minutes after your opponent submitted.",
                $match->resolution_reason === 'admin_resolution' && $hasDishonestyStrike => 'You lost after an admin confirmed dishonest result information or evidence during the dispute review.',
                $match->resolution_reason === 'admin_resolution' => 'You lost after an admin resolved the dispute in your opponent\'s favor.',
                default => 'You lost because your opponent was confirmed as the winner of this match.',
            };
        }

        $activeDispute = $match->disputes->first(fn ($dispute) => $dispute->status !== DisputeStatus::RESOLVED);
        $hasSubmittedDisputeEvidence = $activeDispute !== null && $user !== null
            && $activeDispute->evidence->contains('uploaded_by', $user->id);
        $isResultConflict = $match->status === MatchStatus::DISPUTED
            && $match->resolution_reason === 'conflicting_submissions';

        $latestSubmission = $match->resultSubmissions->first();
        $activeAttempt = $match->attempts->firstWhere('attempt_number', $match->active_attempt_number);
        $isSubmitter = $user && ((int) $match->tournament->workflow_version === 2
            ? $activeAttempt?->submissions->contains('submitted_by', $user->id)
            : $latestSubmission && $user->id === $latestSubmission->submitted_by);
        $submissionUnavailableMessage = null;
        if ((int) $match->tournament->workflow_version === 2 && $isParticipant) {
            $responseMinutes = max(1, (int) ($match->tournament->waiting_result_time ?: 5));
            $submissionUnavailableMessage = match (true) {
                $match->status === MatchStatus::COMPLETED && $match->resolution_reason === 'opponent_submission_timeout' => "Result submission is closed because the {$responseMinutes}-minute response deadline expired.",
                $match->status === MatchStatus::COMPLETED && $match->resolution_reason === 'admin_resolution' => 'Result submission is closed because an admin reviewed and resolved a dispute for this match.',
                $match->status === MatchStatus::COMPLETED && $match->resolution_reason === 'confirmed_submissions' => 'Result submission is closed because both players submitted matching results.',
                in_array($match->status, [MatchStatus::IN_PROGRESS, MatchStatus::WAITING_FOR_CONFIRMATION], true) && $isSubmitter => 'Your result has already been submitted. Waiting for your opponent to report their result.',
                $activeAttempt?->result_deadline_at?->isPast() => 'Your response deadline has expired. Result submission is closed while the match is finalized.',
                $match->status === MatchStatus::DISPUTED => 'Result submission is closed while an admin reviews the dispute.',
                $match->status === MatchStatus::COMPLETED => 'Result submission is closed because this match has already been completed.',
                default => null,
            };
        }

        $isAdmin = Auth::check() && $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER']);
        $layout = $isAdmin ? 'components.layouts.admin' : 'components.layouts.dashboard';

        $view = view('livewire.match.match-detail', [
            'match' => $match,
            'isParticipant' => $isParticipant,
            'isDefeated' => $isDefeated,
            'defeatXp' => $defeatXp,
            'defeatMessage' => $defeatMessage,
            'isSubmitter' => $isSubmitter,
            'submissionUnavailableMessage' => $submissionUnavailableMessage,
            'isAdmin' => $isAdmin,
            'activeDispute' => $activeDispute,
            'hasSubmittedDisputeEvidence' => $hasSubmittedDisputeEvidence,
            'isResultConflict' => $isResultConflict,
            'activeAttempt' => $activeAttempt,
        ]);

        return $this->embedded
            ? $view
            : $view->layout($layout, ['title' => 'Match Room | GamersRival', 'dashboard_title' => 'MATCH ROOM']);
    }
}
