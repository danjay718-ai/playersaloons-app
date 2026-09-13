<?php

namespace App\Livewire\Tournament;

use App\Modules\Tournament\Services\V2TournamentDiscoveryService;
use Livewire\Component;

class PlayerTournamentList extends Component
{
    use TournamentListTrait;

    private const PLAYER_FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public function mount(): void
    {
        if (! in_array($this->frequency, self::PLAYER_FREQUENCIES, true)) {
            $this->frequency = 'daily';
        }
    }

    public function render(V2TournamentDiscoveryService $discovery)
    {
        $usesV2Discovery = (bool) config('features.tournament_v2.enabled');
        $frequencyLabel = ucfirst($this->frequency).' Tournaments';

        return view('livewire.tournament.player-tournament-list', [
            'tournaments' => $usesV2Discovery ? null : $this->getTournamentQuery()->paginate(12),
            'tournamentGroups' => $usesV2Discovery ? $discovery->paginate($this->activeTab, $this->discoveryFilters()) : null,
            'featuredGroups' => $usesV2Discovery ? $discovery->paginate('upcoming', [
                'competition_type' => 'tournament',
                'frequency' => $this->frequency,
            ], $this->featuredLimit, true) : null,
            'games' => $this->getGames(),
            'popularGames' => $this->getPopularGames(),
            'featuredTournaments' => $usesV2Discovery ? collect() : $this->getFeaturedTournaments(),
            'hasMoreFeatured' => ! $usesV2Discovery && $this->featuredTournamentCount() > $this->featuredLimit,
            'platforms' => $this->getPlatforms(),
            'listingType' => 'tournament',
            'allowCompetitionSwitch' => false,
            'fixedFrequency' => true,
        ])->layout('components.layouts.dashboard', [
            'title' => $frequencyLabel.' | PlayerSaloons',
            'dashboard_title' => strtoupper($frequencyLabel),
        ]);
    }

    /** @return array<string, string> */
    private function discoveryFilters(): array
    {
        return [
            'search' => $this->search,
            'game_id' => $this->gameId,
            'frequency' => $this->frequency,
            'competition_type' => 'tournament',
            'platform_id' => $this->platformId,
            'team_format' => $this->teamFormat,
        ];
    }
}
