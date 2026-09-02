<?php

namespace App\Livewire\Tournament;

use App\Modules\Tournament\Services\V2TournamentDiscoveryService;
use Livewire\Component;

class PublicTournamentList extends Component
{
    use TournamentListTrait;

    public function render(V2TournamentDiscoveryService $discovery)
    {
        $usesV2Discovery = (bool) config('features.tournament_v2.enabled');

        return view('livewire.tournament.player-tournament-list', [
            'tournaments' => $usesV2Discovery ? null : $this->getTournamentQuery()->paginate(12),
            'tournamentGroups' => $usesV2Discovery ? $discovery->paginate($this->activeTab, $this->discoveryFilters()) : null,
            'featuredGroups' => $usesV2Discovery ? $discovery->paginate('upcoming', ['competition_type' => $this->competitionType ?: 'tournament'], $this->featuredLimit, true) : null,
            'games' => $this->getGames(),
            'popularGames' => $this->getPopularGames(),
            'featuredTournaments' => $usesV2Discovery ? collect() : $this->getFeaturedTournaments(),
            'hasMoreFeatured' => ! $usesV2Discovery && $this->featuredTournamentCount() > $this->featuredLimit,
            'platforms' => $this->getPlatforms(),
            'listingType' => $this->competitionType === 'head_to_head' ? 'head_to_head' : 'tournament',
            'allowCompetitionSwitch' => true,
        ])->layout('components.layouts.app', ['title' => 'Tournaments | PlayerSaloons']);
    }

    /** @return array<string, string> */
    private function discoveryFilters(): array
    {
        return [
            'search' => $this->search,
            'game_id' => $this->gameId,
            'frequency' => $this->frequency,
            // Guest discovery defaults to tournaments. H2H is an explicit
            // dropdown choice, so the two product lists never blend together.
            'competition_type' => $this->competitionType ?: 'tournament',
            'platform_id' => $this->platformId,
            'team_format' => $this->teamFormat,
        ];
    }
}
