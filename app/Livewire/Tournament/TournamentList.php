<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\Tournament\Models\Tournament;
use Livewire\Component;
use Livewire\WithPagination;

class TournamentList extends Component
{
    use WithPagination;

    public string $status = '';

    public string $gameId = '';

    public string $search = '';

    public string $frequency = 'daily'; // Default is daily

    protected $queryString = [
        'status' => ['except' => ''],
        'gameId' => ['except' => ''],
        'search' => ['except' => ''],
        'frequency' => ['except' => 'daily'],
    ];

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingGameId(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFrequency(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Tournament::query()->with('game.translations');

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->gameId) {
            $query->where('game_id', $this->gameId);
        }

        if ($this->search) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        if ($this->frequency) {
            $query->where('frequency', $this->frequency);
        }

        $tournaments = $query->orderBy('created_at', 'desc')->paginate(9);
        $games = Game::query()->with('translations')->get();

        return view('livewire.tournament.tournament-list', [
            'tournaments' => $tournaments,
            'games' => $games,
        ])->layout('components.layouts.app', ['title' => 'Tournaments | PlayerSaloons']);
    }
}
