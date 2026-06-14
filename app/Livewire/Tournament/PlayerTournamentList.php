<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class PlayerTournamentList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $gameId = '';

    #[Url]
    public string $activeTab = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingGameId(): void
    {
        $this->resetPage();
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage();
    }

    public string $layout = 'components.layouts.dashboard';

    public function mount(string $layout = 'components.layouts.dashboard')
    {
        $this->layout = $layout;
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

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->gameId) {
            $query->where('game_id', $this->gameId);
        }

        if ($this->activeTab !== 'all') {
            $query->where('frequency', $this->activeTab);
        }

        $games = Game::query()->with('translations')->where('is_active', true)->get();

        return view('livewire.tournament.player-tournament-list', [
            'tournaments' => $query->orderBy('created_at', 'desc')->paginate(12),
            'games' => $games,
        ])->layout($this->layout, ['title' => 'Browse Tournaments | PlayerSaloons', 'dashboard_title' => 'BROWSE TOURNAMENTS']);
    }
}
