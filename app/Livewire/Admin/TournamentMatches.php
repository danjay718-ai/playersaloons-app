<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
class TournamentMatches extends AdminComponent
{
    use WithPagination;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('tournaments.view'), 403);
    }

    public $tournamentId;

    public $statusFilter = '';

    public $search = '';

    public function mount($id)
    {
        $this->tournamentId = $id;
        $tournament = Tournament::findOrFail($id);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        $tournament = Tournament::findOrFail($this->tournamentId);

        $matches = GameMatch::with([
            'playerARegistration.user.profile',
            'playerARegistration.team',
            'playerBRegistration.user.profile',
            'playerBRegistration.team',
            'winnerRegistration.user',
        ])
            ->where('tournament_id', $this->tournamentId)
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->search, function ($query) {
                $query->whereHas('playerARegistration.user', function ($q) {
                    $q->where('username', 'like', '%'.$this->search.'%');
                })->orWhereHas('playerBRegistration.user', function ($q) {
                    $q->where('username', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('livewire.admin.tournament-matches', [
            'tournament' => $tournament,
            'matches' => $matches,
        ]);
    }
}
