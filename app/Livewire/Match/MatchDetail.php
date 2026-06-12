<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use App\Modules\Match\Actions\OpenDisputeAction;
use App\Modules\Match\Actions\SubmitEvidenceAction;
use App\Modules\Match\Actions\SubmitMatchResultAction;
use App\Modules\Match\Models\GameMatch;
use App\Shared\Enums\DisputeStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class MatchDetail extends Component
{
    use WithFileUploads;

    public string $uuid;

    public ?int $winnerRegistrationId = null;

    public string $notes = '';

    public $evidenceFile;

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
        ]);

        try {
            $action->execute(
                $match,
                (int) Auth::id(),
                (int) $this->winnerRegistrationId,
                $this->notes
            );
            session()->flash('message', 'Result submitted successfully!');
            $this->reset(['winnerRegistrationId', 'notes']);
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

        try {
            $action->execute($match, (int) Auth::id());
            session()->flash('message', 'Dispute opened successfully. Please upload screenshots as evidence below.');
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

        $user = Auth::user();
        $isParticipant = $user && (
            $user->id === $match->playerARegistration?->user_id ||
            $user->id === $match->playerBRegistration?->user_id
        );

        $activeDispute = $match->disputes()
            ->where('status', '!=', DisputeStatus::RESOLVED->value)
            ->first();

        return view('livewire.match.match-detail', [
            'match' => $match,
            'isParticipant' => $isParticipant,
            'activeDispute' => $activeDispute,
        ])->layout('components.layouts.app', ['title' => 'Match Hub | PlayerSaloons']);
    }
}
