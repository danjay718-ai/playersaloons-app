<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use App\Livewire\Concerns\HandlesUserFacingErrors;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\AutoForfeitAction;
use App\Modules\Match\Actions\ConfirmMatchResultAction;
use App\Modules\Match\Actions\OpenDisputeAction;
use App\Modules\Match\Actions\SubmitEvidenceAction;
use App\Modules\Match\Actions\SubmitMatchResultAction;
use App\Modules\Match\Actions\VoteForRematchAction;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class MatchDetail extends Component
{
    use HandlesUserFacingErrors;
    use WithFileUploads;

    public string $uuid;

    public ?int $winnerRegistrationId = null;

    public string $notes = '';

    public string $disputeReason = '';

    public ?TemporaryUploadedFile $evidenceFile = null;

    public ?TemporaryUploadedFile $submissionProof = null;

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
            session()->flash('error', $this->safeError($e, 'Unable to submit the match result.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to confirm the match result.'));
        }
    }

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function submitResult(SubmitMatchResultAction $action)
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
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to dispute the match result.'));
        }
    }

    public function openDispute(OpenDisputeAction $action)
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
        ]);

        try {
            $action->execute($match, (int) Auth::id(), $this->disputeReason);
            session()->flash('message', 'Dispute opened successfully. Please upload screenshots as evidence below.');
            $this->reset('disputeReason');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to submit match evidence.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to cast the rematch vote.'));
        }
    }

    public function render()
    {
        $match = GameMatch::query()
            ->where('uuid', $this->uuid)
            ->with([
                'tournament',
                'round',
                'playerARegistration.user.profile',
                'playerARegistration.team',
                'playerBRegistration.user.profile',
                'playerBRegistration.team',
                'winnerRegistration.user.profile',
                'resultSubmissions.user',
                'disputes' => function ($q) {
                    $q->with('evidence');
                },
                'rematchVotes',
            ])
            ->firstOrFail();

        // JIT Timeout Check
        if ($match->isTimedOut()) {
            app(AutoForfeitAction::class)->execute($match);
            $match->refresh();
        }

        /** @var User|null $user */
        $user = Auth::user();
        $isParticipant = $user && (
            $match->playerARegistration?->includesUser($user->id)
            || $match->playerBRegistration?->includesUser($user->id)
        );

        $activeDispute = $match->disputes()
            ->where('status', '!=', DisputeStatus::RESOLVED->value)
            ->first();

        $latestSubmission = $match->resultSubmissions()->latest()->first();
        $isSubmitter = $user && $latestSubmission && $user->id === $latestSubmission->submitted_by;

        $isAdmin = Auth::check() && $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER']);
        $layout = $isAdmin ? 'components.layouts.admin' : 'components.layouts.dashboard';

        return view('livewire.match.match-detail', [
            'match' => $match,
            'isParticipant' => $isParticipant,
            'isSubmitter' => $isSubmitter,
            'isAdmin' => $isAdmin,
            'activeDispute' => $activeDispute,
        ])->layout($layout, ['title' => 'Match Hub | PlayerSaloons', 'dashboard_title' => 'MATCH HUB']);
    }
}
