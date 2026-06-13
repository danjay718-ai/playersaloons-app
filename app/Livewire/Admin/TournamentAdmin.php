<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\Game;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Actions\CloseCheckinAction;
use App\Modules\Tournament\Actions\CloseRegistrationAction;
use App\Modules\Tournament\Actions\CompleteTournamentAction;
use App\Modules\Tournament\Actions\ProcessRefundAction;
use App\Modules\Tournament\Actions\PublishTournamentAction;
use App\Modules\Tournament\Actions\StartTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class TournamentAdmin extends AdminComponent
{
    use WithPagination, WithFileUploads;

    public string $search = '';

    public string $statusFilter = '';

    public string $gameFilter = '';

    // Modal control
    public bool $showDetailModal = false;
    public bool $showCancelModal = false;

    // Selected ID
    public ?int $selectedTournamentId = null;

    // Cancel form
    public string $cancelReason = '';

    public string $cancelNotes = '';

    protected $paginationTheme = 'tailwind';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingGameFilter(): void
    {
        $this->resetPage();
    }

    public function selectTournament(int $id): void
    {
        $this->selectedTournamentId = $id;
        $this->showDetailModal = true;
    }

    // Lifecycle transitions
    public function applyTransition(string $transitionName): void
    {
        if (! $this->selectedTournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->selectedTournamentId);

        try {
            switch ($transitionName) {
                case 'publish':
                    app(PublishTournamentAction::class)->execute($tournament);
                    break;
                case 'open_registration':
                    app(OpenRegistrationAction::class)->execute($tournament);
                    break;
                case 'close_registration':
                    app(CloseRegistrationAction::class)->execute($tournament);
                    break;
                case 'open_checkin':
                    app(OpenCheckinAction::class)->execute($tournament);
                    break;
                case 'close_checkin':
                    app(CloseCheckinAction::class)->execute($tournament);
                    break;
                case 'generate_bracket':
                    app(GenerateBracketAction::class)->execute($tournament);
                    break;
                case 'start':
                    app(StartTournamentAction::class)->execute($tournament);
                    break;
                case 'complete':
                    app(CompleteTournamentAction::class)->execute($tournament);
                    break;
                case 'process_refund':
                    app(ProcessRefundAction::class)->execute($tournament);
                    break;
            }
            session()->flash('success', 'State transition executed successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Transition failed: '.$e->getMessage());
        }
    }

    public function openCancelModal(int $id): void
    {
        $this->selectedTournamentId = $id;
        $this->cancelReason = '';
        $this->cancelNotes = '';
        $this->showCancelModal = true;
    }

    public function cancelTournament(CancelTournamentAction $cancelAction): void
    {
        $this->validate([
            'cancelReason' => 'required|string|min:5|max:255',
            'cancelNotes' => 'nullable|string',
        ]);

        if (! $this->selectedTournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->selectedTournamentId);
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        try {
            $cancelAction->execute($tournament, $actor, $this->cancelReason, $this->cancelNotes);
            session()->flash('success', 'Tournament cancelled and refunds processed successfully.');
            $this->showCancelModal = false;
            $this->showDetailModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Cancellation failed: '.$e->getMessage());
        }
    }

    public function deleteTournament(int $id): void
    {
        $tournament = Tournament::withCount('registrations')->findOrFail($id);

        if ($tournament->status !== TournamentStatus::DRAFT) {
            session()->flash('error', 'Only draft tournaments can be deleted.');
            return;
        }

        if ($tournament->registrations_count > 0) {
            session()->flash('error', 'Cannot delete a tournament that has registrations.');
            return;
        }

        $tournament->delete();
        session()->flash('success', 'Tournament deleted successfully.');
        $this->showDetailModal = false;
    }

    public function render()
    {
        $query = Tournament::query()
            ->with(['game.translations', 'registrations', 'creator', 'platform'])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->gameFilter) {
            $query->where('game_id', $this->gameFilter);
        }

        $tournaments = $query->paginate(10);
        $games = Game::with('translations')->get();

        $selectedTournament = $this->selectedTournamentId
            ? Tournament::with(['game.translations', 'registrations.user', 'cancellation.cancelledBy', 'rounds.matches', 'platform'])->find($this->selectedTournamentId)
            : null;

        return view('livewire.admin.tournament-admin', [
            'tournaments' => $tournaments,
            'games' => $games,
            'selectedTournament' => $selectedTournament,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Tournament Management',
        ]);
    }
}
