<?php

namespace App\Livewire\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

trait TournamentListTrait
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

    protected function getTournamentQuery()
    {
        $query = Tournament::query()
            ->with(['game.translations', 'platform'])
            ->withCount(['registrations' => function ($q) {
                $q->whereNotIn('status', ['cancelled', 'refunded']);
            }])
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

        return $query->orderBy('created_at', 'desc');
    }

    protected function getGames()
    {
        return Game::query()->with('translations')->where('is_active', true)->get();
    }
}
