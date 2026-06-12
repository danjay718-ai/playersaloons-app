<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\CMS\Models\Game;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Actions\CloseCheckinAction;
use App\Modules\Tournament\Actions\CloseRegistrationAction;
use App\Modules\Tournament\Actions\CompleteTournamentAction;
use App\Modules\Tournament\Actions\CreateTournamentAction;
use App\Modules\Tournament\Actions\GenerateBracketAction;
use App\Modules\Tournament\Actions\OpenCheckinAction;
use App\Modules\Tournament\Actions\OpenRegistrationAction;
use App\Modules\Tournament\Actions\ProcessRefundAction;
use App\Modules\Tournament\Actions\PublishTournamentAction;
use App\Modules\Tournament\Actions\StartTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;

class TournamentAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $gameFilter = '';

    // Modal control
    public bool $showCreateModal = false;
    public bool $showDetailModal = false;
    public bool $showCancelModal = false;
    public bool $isEditMode = false;

    // Selected ID
    public ?int $selectedTournamentId = null;

    // Form fields
    public string $name = '';
    public int $game_id = 0;
    public int $max_participants = 16;
    public int $min_participants = 4;
    public string $entry_fee = '0.00';
    public string $prize_pool = '0.00';
    public string $registration_open_at = '';
    public string $registration_close_at = '';
    public string $checkin_open_at = '';
    public string $checkin_close_at = '';
    public string $start_at = '';

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

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->isEditMode = false;
        $this->showCreateModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->resetForm();
        $this->isEditMode = true;
        $this->selectedTournamentId = $id;
        $tournament = Tournament::findOrFail($id);

        if ($tournament->status !== TournamentStatus::DRAFT) {
            session()->flash('error', 'Only draft tournaments can be edited.');
            return;
        }

        $this->name = $tournament->name;
        $this->game_id = (int) $tournament->game_id;
        $this->max_participants = (int) $tournament->max_participants;
        $this->min_participants = (int) $tournament->min_participants;
        $this->entry_fee = (string) $tournament->entry_fee;
        $this->prize_pool = (string) $tournament->prize_pool;
        $this->registration_open_at = $tournament->registration_open_at ? $tournament->registration_open_at->format('Y-m-d\TH:i') : '';
        $this->registration_close_at = $tournament->registration_close_at ? $tournament->registration_close_at->format('Y-m-d\TH:i') : '';
        $this->checkin_open_at = $tournament->checkin_open_at ? $tournament->checkin_open_at->format('Y-m-d\TH:i') : '';
        $this->checkin_close_at = $tournament->checkin_close_at ? $tournament->checkin_close_at->format('Y-m-d\TH:i') : '';
        $this->start_at = $tournament->start_at ? $tournament->start_at->format('Y-m-d\TH:i') : '';

        $this->showCreateModal = true;
    }

    public function saveTournament(CreateTournamentAction $createAction): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'game_id' => 'required|exists:games,id',
            'max_participants' => 'required|integer|min:2',
            'min_participants' => 'required|integer|min:2|lte:max_participants',
            'entry_fee' => 'required|numeric|min:0',
            'prize_pool' => 'required|numeric|min:0',
            'registration_open_at' => 'required|date',
            'registration_close_at' => 'required|date|after:registration_open_at',
            'checkin_open_at' => 'required|date|after:registration_close_at',
            'checkin_close_at' => 'required|date|after:checkin_open_at',
            'start_at' => 'required|date|after:checkin_close_at',
        ]);

        $creator = Auth::user();
        if (!$creator) {
            return;
        }

        $data = [
            'name' => $this->name,
            'game_id' => $this->game_id,
            'max_participants' => $this->max_participants,
            'min_participants' => $this->min_participants,
            'entry_fee' => $this->entry_fee,
            'prize_pool' => $this->prize_pool,
            'registration_open_at' => $this->registration_open_at,
            'registration_close_at' => $this->registration_close_at,
            'checkin_open_at' => $this->checkin_open_at,
            'checkin_close_at' => $this->checkin_close_at,
            'start_at' => $this->start_at,
        ];

        if ($this->isEditMode && $this->selectedTournamentId) {
            $tournament = Tournament::findOrFail($this->selectedTournamentId);
            if ($tournament->status !== TournamentStatus::DRAFT) {
                session()->flash('error', 'Only draft tournaments can be edited.');
                return;
            }
            $tournament->update($data);
            session()->flash('success', 'Tournament updated successfully.');
        } else {
            $createAction->execute($data, $creator);
            session()->flash('success', 'Tournament created successfully.');
        }

        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->game_id = Game::first()?->id ?? 0;
        $this->max_participants = 16;
        $this->min_participants = 4;
        $this->entry_fee = '0.00';
        $this->prize_pool = '0.00';
        $this->registration_open_at = '';
        $this->registration_close_at = '';
        $this->checkin_open_at = '';
        $this->checkin_close_at = '';
        $this->start_at = '';
        $this->isEditMode = false;
        $this->selectedTournamentId = null;
    }

    // Lifecycle transitions
    public function applyTransition(string $transitionName): void
    {
        if (!$this->selectedTournamentId) return;

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
            session()->flash('error', 'Transition failed: ' . $e->getMessage());
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

        if (!$this->selectedTournamentId) return;

        $tournament = Tournament::findOrFail($this->selectedTournamentId);
        $actor = Auth::user();

        if (!$actor) return;

        try {
            $cancelAction->execute($tournament, $actor, $this->cancelReason, $this->cancelNotes);
            session()->flash('success', 'Tournament cancelled and refunds processed successfully.');
            $this->showCancelModal = false;
            $this->showDetailModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Cancellation failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = Tournament::query()
            ->with(['game.translations', 'registrations', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
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
            ? Tournament::with(['game.translations', 'registrations.user', 'cancellation.cancelledBy', 'rounds.matches'])->find($this->selectedTournamentId) 
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
