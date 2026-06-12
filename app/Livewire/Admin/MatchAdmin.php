<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Match\Actions\ResolveDisputeAction;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class MatchAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $disputeFilter = false;

    // Modal state
    public bool $showDetailModal = false;

    public bool $showOverrideModal = false;

    public bool $showDisputeModal = false;

    // Selection
    public ?int $selectedMatchId = null;

    public ?int $selectedDisputeId = null;

    // Forms
    public ?int $winnerRegistrationId = null;

    public string $resolution = ''; // string mapped to DisputeResolution

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        // Check query parameter for initial filter
        if (request()->query('filter') === 'disputes') {
            $this->disputeFilter = true;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDisputeFilter(): void
    {
        $this->resetPage();
    }

    public function selectMatch(int $id): void
    {
        $this->selectedMatchId = $id;
        $this->winnerRegistrationId = null;
        $this->showDetailModal = true;
    }

    public function openOverrideModal(int $id): void
    {
        $this->selectedMatchId = $id;
        $match = GameMatch::findOrFail($id);
        $this->winnerRegistrationId = (int) ($match->winner_registration_id ?? $match->player_a_registration_id);
        $this->showOverrideModal = true;
    }

    public function openDisputeModal(int $disputeId): void
    {
        $this->selectedDisputeId = $disputeId;
        $this->resolution = '';
        $this->showDisputeModal = true;
    }

    public function overrideResult(MatchStateMachine $stateMachine): void
    {
        $this->validate([
            'winnerRegistrationId' => 'required|integer',
        ]);

        $match = GameMatch::findOrFail($this->selectedMatchId);

        if ($this->winnerRegistrationId != $match->player_a_registration_id && $this->winnerRegistrationId != $match->player_b_registration_id) {
            session()->flash('error', 'Winner must be one of the match participants.');

            return;
        }

        try {
            DB::transaction(function () use ($match, $stateMachine) {
                // If it is disputed, let's close the dispute record first to maintain state consistency
                if ($match->status === MatchStatus::DISPUTED) {
                    $dispute = MatchDispute::where('match_id', $match->id)
                        ->where('status', DisputeStatus::OPEN)
                        ->first();
                    if ($dispute) {
                        $dispute->status = DisputeStatus::RESOLVED;
                        $dispute->resolution = $this->winnerRegistrationId == $match->player_a_registration_id
                            ? DisputeResolution::PLAYER_A
                            : DisputeResolution::PLAYER_B;
                        $dispute->resolved_by = Auth::id();
                        $dispute->resolved_at = now();
                        $dispute->save();
                    }
                }

                $match->winner_registration_id = $this->winnerRegistrationId;
                $match->save();

                // Bring the match to COMPLETED state safely depending on its current state
                if ($match->status === MatchStatus::PENDING) {
                    $stateMachine->transition($match, MatchStatus::READY);
                }
                if ($match->status === MatchStatus::READY) {
                    $stateMachine->transition($match, MatchStatus::IN_PROGRESS);
                }
                if ($match->status === MatchStatus::IN_PROGRESS) {
                    $stateMachine->transition($match, MatchStatus::RESULT_SUBMITTED);
                }
                if ($match->status === MatchStatus::RESULT_SUBMITTED || $match->status === MatchStatus::DISPUTED) {
                    $stateMachine->transition($match, MatchStatus::COMPLETED);
                }

                MatchCompleted::dispatch(
                    $match->id,
                    $match->tournament_id,
                    $this->winnerRegistrationId
                );
            });

            session()->flash('success', 'Match result overridden and advanced successfully.');
            $this->showOverrideModal = false;
            $this->showDetailModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Override failed: '.$e->getMessage());
        }
    }

    public function resolveDispute(ResolveDisputeAction $resolver): void
    {
        $this->validate([
            'resolution' => 'required|string|in:player_a,player_b,rematch',
        ]);

        if (! $this->selectedDisputeId) {
            return;
        }

        $dispute = MatchDispute::findOrFail($this->selectedDisputeId);
        $resolutionEnum = DisputeResolution::from($this->resolution);
        $adminId = (int) Auth::id();

        try {
            $resolver->execute($dispute, $adminId, $resolutionEnum);
            session()->flash('success', 'Dispute resolved successfully.');
            $this->showDisputeModal = false;
            $this->showDetailModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to resolve dispute: '.$e->getMessage());
        }
    }

    public function render()
    {
        $query = GameMatch::query()
            ->with(['tournament.game.translations', 'playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration.user', 'disputes'])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->whereHas('tournament', function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%');
            })->orWhereHas('playerARegistration.user', function ($q) {
                $q->where('username', 'like', '%'.$this->search.'%');
            })->orWhereHas('playerBRegistration.user', function ($q) {
                $q->where('username', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->disputeFilter) {
            $query->where('status', MatchStatus::DISPUTED->value);
        }

        $matches = $query->paginate(15);

        $selectedMatch = $this->selectedMatchId
            ? GameMatch::with([
                'tournament.game.translations',
                'playerARegistration.user',
                'playerBRegistration.user',
                'winnerRegistration.user',
                'disputes.openedBy',
                'disputes.evidence.uploadedBy',
            ])->find($this->selectedMatchId)
            : null;

        return view('livewire.admin.match-admin', [
            'matches' => $matches,
            'selectedMatch' => $selectedMatch,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Match & Dispute Control',
        ]);
    }
}
