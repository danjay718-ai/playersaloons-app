<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use App\Modules\Match\Actions\AutoForfeitAction;
use App\Modules\Match\Actions\ConfirmMatchResultAction;
use App\Modules\Match\Actions\OpenDisputeAction;
use App\Modules\Match\Actions\SubmitEvidenceAction;
use App\Modules\Match\Actions\SubmitMatchResultAction;
use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class MatchDetail extends Component
{
    use WithFileUploads;

    public string $uuid;

    public ?int $winnerRegistrationId = null;

    public string $notes = '';

    public string $disputeReason = '';

    public $evidenceFile;

    public $submissionProof;

    public function confirmResult(ConfirmMatchResultAction $action)
    {
        // ... (existing method)
    }

    public function adminCompleteMatch(int $winnerId)
    {
        if (! Auth::user()->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER'])) {
            return;
        }

        $match = GameMatch::query()->where('uuid', $this->uuid)->firstOrFail();

        try {
            DB::transaction(function () use ($match, $winnerId) {
                $match->winner_registration_id = $winnerId;
                $match->status = MatchStatus::COMPLETED;
                $match->completed_at = now();
                $match->save();

                \App\Modules\Match\Events\MatchCompleted::dispatch($match);
            });
            session()->flash('message', 'Match finalized by administrator.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
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

        if (! Auth::check() || ! Auth::user()->can('submitResult', $match)) {
            session()->flash('error', 'You are not authorized to submit results for this match.');

            return;
        }

        $this->validate([
            'winnerRegistrationId' => ['required', 'integer', 'in:'.$match->player_a_registration_id.','.$match->player_b_registration_id],
            'notes' => ['nullable', 'string', 'max:500'],
            'submissionProof' => ['nullable', 'file', 'max:10240', 'mimes:png,jpg,jpeg,webp,pdf'],
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
            session()->flash('error', $e->getMessage());
        }
    }

    public function openDispute(OpenDisputeAction $action)
    {
        $match = GameMatch::query()->where('uuid', $this->uuid)->firstOrFail();

        if (! Auth::check() || ! Auth::user()->can('dispute', $match)) {
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
            session()->flash('error', $e->getMessage());
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

        if (! Auth::check() || (Auth::id() !== $match->playerARegistration?->user_id && Auth::id() !== $match->playerBRegistration?->user_id)) {
            session()->flash('error', 'You are not authorized to submit evidence.');

            return;
        }

        $this->validate([
            'evidenceFile' => ['required', 'file', 'max:20480', 'mimes:png,jpg,jpeg,webp,pdf,mp4,mov'],
        ]);

        try {
            $action->execute($dispute, (int) Auth::id(), $this->evidenceFile);
            session()->flash('message', 'Evidence uploaded successfully! The tournament admins will review it.');
            $this->reset('evidenceFile');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
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
                'playerBRegistration.user.profile',
                'winnerRegistration.user.profile',
                'resultSubmissions.user',
                'disputes' => function ($q) {
                    $q->with('evidence');
                },
            ])
            ->firstOrFail();

        // JIT Timeout Check
        if ($match->isTimedOut()) {
            app(AutoForfeitAction::class)->execute($match);
            $match->refresh();
        }

        $user = Auth::user();
        $isParticipant = $user && (
            $user->id === $match->playerARegistration?->user_id ||
            $user->id === $match->playerBRegistration?->user_id
        );

        $activeDispute = $match->disputes()
            ->where('status', '!=', DisputeStatus::RESOLVED->value)
            ->first();
            
        $latestSubmission = $match->resultSubmissions()->latest()->first();
        $isSubmitter = $user && $latestSubmission && $user->id === $latestSubmission->submitted_by;
            
        $isAdmin = Auth::check() && Auth::user()->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'TOURNAMENT_ORGANIZER']);
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
