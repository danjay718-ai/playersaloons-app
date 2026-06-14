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

    public string $frequency = '';

    public string $platformId = '';

    protected $queryString = [
        'status' => ['except' => ''],
        'gameId' => ['except' => ''],
        'search' => ['except' => ''],
        'frequency' => ['except' => ''],
        'platformId' => ['except' => ''],
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

    public function updatingPlatformId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Tournament::query()
            ->with(['game.translations', 'platform'])
            ->whereIn('status', [
                TournamentStatus::REGISTRATION_OPEN->value,
                TournamentStatus::REGISTRATION_CLOSED->value,
                TournamentStatus::CHECKIN_OPEN->value,
                TournamentStatus::CHECKIN_CLOSED->value,
                TournamentStatus::BRACKET_GENERATED->value,
                TournamentStatus::ONGOING->value,
            ]);

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

        if ($this->platformId) {
            $query->where('platform_id', $this->platformId);
        }

        $tournaments = $query->orderBy('created_at', 'desc')->paginate(9);
        $games = Game::query()->with('translations')->get();
        $platforms = \App\Modules\CMS\Models\Platform::where('is_active', true)->get();

        return view('livewire.tournament.tournament-list', [
            'tournaments' => $tournaments,
            'games' => $games,
            'platforms' => $platforms,
        ])->layout('components.layouts.app', ['title' => 'Tournaments | PlayerSaloons']);
    }
}
