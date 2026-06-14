<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Livewire\Component;
use Livewire\WithPagination;

class PlayerTournamentList extends Component
{
    use WithPagination;

    public string $status = '';
    public string $gameId = '';
    public string $search = '';
    public string $frequency = '';
    public string $platformId = '';

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

        return view('livewire.tournament.player-tournament-list', [
            'tournaments' => $query->orderBy('created_at', 'desc')->paginate(12),
        ])->layout('components.layouts.dashboard', ['title' => 'Browse Tournaments | PlayerSaloons', 'dashboard_title' => 'BROWSE TOURNAMENTS']);
    }
}
