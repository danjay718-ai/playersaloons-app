<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use App\Livewire\Concerns\HandlesUserFacingErrors;
use App\Modules\Identity\Models\PlayerExperienceAward;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\AutoForfeitAction;
use App\Modules\Match\Actions\ConfirmMatchResultAction;
use App\Modules\Match\Actions\OpenDisputeAction;
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

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $match = GameMatch::query()->where('uuid', $uuid)->with('tournament:id,workflow_version')->firstOrFail([
            'id', 'tournament_id', 'lobby_code', 'lobby_password', 'server_region', 'lobby_instructions',
        ]);
        abort_if((int) $match->tournament->workflow_version === 2 && ! config('features.tournament_v2.enabled'), 404);
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
                session()->flash('message', 'Result submitted. Your opponent has five minutes from the first submission to respond.');
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
            'disputeReason' => 'required|string|min:10',
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
            $this->dispatch('match-dispute-opened');
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

    public function render()
    {
        $match = GameMatch::query()
            ->where('uuid', $this->uuid)
            ->with([
                'round',
                'playerARegistration.user.profile',
                'playerARegistration.team',
                'playerARegistration.rosterMembers',
                'playerARegistration.tournamentTeam.members.user:id,username',
                'playerBRegistration.user.profile',
                'playerBRegistration.team',
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

        $activeDispute = $match->disputes->first(fn ($dispute) => $dispute->status !== DisputeStatus::RESOLVED);
        $hasSubmittedDisputeEvidence = $activeDispute !== null && $user !== null
            && $activeDispute->evidence->contains('uploaded_by', $user->id);

        $latestSubmission = $match->resultSubmissions->first();
        $activeAttempt = $match->attempts->firstWhere('attempt_number', $match->active_attempt_number);
        $isSubmitter = $user && ((int) $match->tournament->workflow_version === 2
            ? $activeAttempt?->submissions->contains('submitted_by', $user->id)
            : $latestSubmission && $user->id === $latestSubmission->submitted_by);

        $isAdmin = Auth::check() && $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER']);
        $layout = $isAdmin ? 'components.layouts.admin' : 'components.layouts.dashboard';

        return view('livewire.match.match-detail', [
            'match' => $match,
            'isParticipant' => $isParticipant,
            'isDefeated' => $isDefeated,
            'defeatXp' => $defeatXp,
            'isSubmitter' => $isSubmitter,
            'isAdmin' => $isAdmin,
            'activeDispute' => $activeDispute,
            'hasSubmittedDisputeEvidence' => $hasSubmittedDisputeEvidence,
            'activeAttempt' => $activeAttempt,
        ])->layout($layout, ['title' => 'Match Room | PlayerSaloons', 'dashboard_title' => 'MATCH ROOM']);
    }
}
