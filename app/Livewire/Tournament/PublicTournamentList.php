<?php

namespace App\Livewire\Tournament;

use Livewire\Component;

class PublicTournamentList extends Component
{
    use TournamentListTrait;

    public function render()
    {
        return view('livewire.tournament.player-tournament-list', [
            'tournaments' => $this->getTournamentQuery()->paginate(12),
            'games' => $this->getGames(),
            'popularGames' => $this->getPopularGames(),
            'featuredTournaments' => $this->getFeaturedTournaments(),
            'hasMoreFeatured' => $this->featuredTournamentCount() > $this->featuredLimit,
            'platforms' => $this->getPlatforms(),
        ])->layout('components.layouts.app', ['title' => 'Tournaments | PlayerSaloons']);
    }
}
