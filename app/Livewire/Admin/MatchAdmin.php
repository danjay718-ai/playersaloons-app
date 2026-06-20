<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\Game;
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
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

class MatchAdmin extends AdminComponent
{
    use WithPagination;

    // Filters
    public string $search = '';
    public string $statusFilter = '';
    public string $gameFilter = '';
    public bool $disputeFilter = false;
    public int $perPage = 15;

    // Modal state
    public bool $showDetailModal = false;
    public bool $showOverrideModal = false;
    public bool $showDisputeModal = false;

    // Selection
    public ?int $selectedMatchId = null;
    public ?int $selectedDisputeId = null;

    // Forms
    public ?int $winnerRegistrationId = null;
    public string $resolution = '';

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        if (request()->query('filter') === 'disputes') {
            $this->disputeFilter = true;
        }
    }

    // ─── Reset page on filter changes ────────────────────────────────────────

    public function updatingSearch(): void       { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingGameFilter(): void   { $this->resetPage(); }
    public function updatingDisputeFilter(): void{ $this->resetPage(); }
    public function updatingPerPage(): void      { $this->resetPage(); }

    // ─── Modal openers ────────────────────────────────────────────────────────

    public function selectMatch(int $id): void
    {
        $this->selectedMatchId    = $id;
        $this->winnerRegistrationId = null;
        $this->showDetailModal    = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal  = false;
        $this->showOverrideModal = false;
        $this->selectedMatchId  = null; // free the heavy query on next render
    }

    public function openOverrideModal(int $id): void
    {
        $this->selectedMatchId = $id;
        $match = GameMatch::select(['id', 'winner_registration_id', 'player_a_registration_id'])
            ->findOrFail($id);
        $this->winnerRegistrationId = (int) ($match->winner_registration_id ?? $match->player_a_registration_id);
        $this->showOverrideModal = true;
    }

    public function openDisputeModal(int $disputeId): void
    {
        $this->selectedDisputeId = $disputeId;
        $this->resolution        = '';
        $this->showDisputeModal  = true;
    }

    public function closeDisputeModal(): void
    {
        $this->showDisputeModal  = false;
        $this->selectedDisputeId = null; // free the query on next render
    }

    // ─── Actions ─────────────────────────────────────────────────────────────

    public function overrideResult(MatchStateMachine $stateMachine): void
    {
        $this->validate(['winnerRegistrationId' => 'required|integer']);

        $match = GameMatch::findOrFail($this->selectedMatchId);

        $actor = Auth::user();
        if (! $actor || ! $actor->can('submitResult', $match)) {
            abort(403);
        }

        if ($this->winnerRegistrationId !== $match->player_a_registration_id
            && $this->winnerRegistrationId !== $match->player_b_registration_id) {
            session()->flash('error', 'Winner must be one of the match participants.');
            return;
        }

        try {
            DB::transaction(function () use ($match, $stateMachine) {
                if ($match->status === MatchStatus::DISPUTED) {
                    $dispute = MatchDispute::where('match_id', $match->id)
                        ->where('status', DisputeStatus::OPEN)
                        ->first();
                    if ($dispute) {
                        $dispute->status      = DisputeStatus::RESOLVED;
                        $dispute->resolution  = $this->winnerRegistrationId === $match->player_a_registration_id
                            ? DisputeResolution::PLAYER_A
                            : DisputeResolution::PLAYER_B;
                        $dispute->resolved_by = Auth::id();
                        $dispute->resolved_at = now();
                        $dispute->save();
                    }
                }

                $match->winner_registration_id = $this->winnerRegistrationId;
                $match->save();

                if ($match->status === MatchStatus::PENDING)           { $stateMachine->transition($match, MatchStatus::READY); }
                if ($match->status === MatchStatus::READY)             { $stateMachine->transition($match, MatchStatus::IN_PROGRESS); }
                if ($match->status === MatchStatus::IN_PROGRESS)       { $stateMachine->transition($match, MatchStatus::WAITING_FOR_CONFIRMATION); }
                if ($match->status === MatchStatus::WAITING_FOR_CONFIRMATION
                    || $match->status === MatchStatus::RESULT_SUBMITTED
                    || $match->status === MatchStatus::DISPUTED)       { $stateMachine->transition($match, MatchStatus::COMPLETED); }

                MatchCompleted::dispatch($match->id, $match->tournament_id, $this->winnerRegistrationId);
            });

            session()->flash('success', 'Match result overridden and advanced successfully.');
            $this->closeDetailModal();
        } catch (\Exception $e) {
            session()->flash('error', 'Override failed: ' . $e->getMessage());
        }
    }

    public function resolveDispute(ResolveDisputeAction $resolver): void
    {
        $this->validate(['resolution' => 'required|string|in:player_a,player_b,rematch']);

        if (! $this->selectedDisputeId) { return; }

        $dispute       = MatchDispute::findOrFail($this->selectedDisputeId);
        $resolutionEnum = DisputeResolution::from($this->resolution);
        $actor         = Auth::user();

        if (! $actor) {
            return;
        }

        try {
            $resolver->execute($dispute, $actor, $resolutionEnum);
            session()->flash('success', 'Dispute resolved successfully.');
            $this->closeDisputeModal();
            $this->closeDetailModal();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to resolve dispute: ' . $e->getMessage());
        }
    }

    // ─── Computed properties (only run when modal is open) ───────────────────

    /**
     * Load the selected match with full relations — only when detail modal is open.
     * Cached per request via #[Computed].
     */
    #[Computed]
    public function selectedMatch(): ?GameMatch
    {
        if (! $this->showDetailModal || ! $this->selectedMatchId) {
            return null;
        }

        return GameMatch::with([
            'tournament.game.translations',
            'playerARegistration.user',
            'playerBRegistration.user',
            'winnerRegistration.user',
            'round',
            'disputes.openedBy',
            'disputes.evidence.uploadedBy',
        ])->find($this->selectedMatchId);
    }

    /**
     * Load the selected dispute for the resolve modal — only when that modal is open.
     * Cached per request via #[Computed].
     */
    #[Computed]
    public function selectedDispute(): ?MatchDispute
    {
        if (! $this->showDisputeModal || ! $this->selectedDisputeId) {
            return null;
        }

        return MatchDispute::with([
            'match.playerARegistration.user',
            'match.playerBRegistration.user',
            'openedBy',
            'evidence.uploadedBy',
        ])->find($this->selectedDisputeId);
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        // Only eager-load 'disputes' on the list when the dispute filter or dispute actions need it
        $with = ['tournament.game.translations', 'playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration.user'];
        if ($this->disputeFilter || ! $this->statusFilter) {
            // include disputes for the quick-action gavel button in rows
            $with[] = 'disputes';
        }

        $query = GameMatch::query()
            ->with($with)
            ->orderBy('updated_at', 'desc');

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('tournament', fn ($tq) => $tq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('playerARegistration.user', fn ($uq) => $uq->where('username', 'like', "%{$search}%"))
                  ->orWhereHas('playerBRegistration.user', fn ($uq) => $uq->where('username', 'like', "%{$search}%"));
            });
        }

        if ($this->gameFilter) {
            $query->whereHas('tournament', fn ($q) => $q->where('game_id', $this->gameFilter));
        }

        if ($this->disputeFilter) {
            $query->where('status', MatchStatus::DISPUTED->value);
        } elseif ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        $matches = $query->paginate($this->perPage);
        $games   = Game::with('translations')->orderBy('slug')->get();

        return view('livewire.admin.match-admin', [
            'matches' => $matches,
            'games'   => $games,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Match & Dispute Control',
        ]);
    }
}
