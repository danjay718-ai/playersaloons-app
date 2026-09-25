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
        $isHeadToHeadListing = $this->competitionType === 'head_to_head';
        $usesGroupedDiscovery = $usesV2Discovery && $isHeadToHeadListing;
        $featuredTournaments = $usesV2Discovery && ! $isHeadToHeadListing
            ? $discovery->paginateOccurrences('upcoming', ['competition_type' => 'tournament'], $this->featuredLimit, true, 'featuredPage')
            : ($usesV2Discovery ? collect() : $this->getFeaturedTournaments());

        return view('livewire.tournament.player-tournament-list', [
            'tournaments' => $usesV2Discovery
                ? ($usesGroupedDiscovery ? null : $discovery->paginateOccurrences($this->activeTab, $this->discoveryFilters()))
                : $this->getTournamentQuery()->paginate(12),
            'tournamentGroups' => $usesGroupedDiscovery ? $discovery->paginate($this->activeTab, $this->discoveryFilters()) : null,
            'featuredGroups' => $usesGroupedDiscovery
                ? $discovery->paginate('upcoming', ['competition_type' => 'head_to_head'], $this->featuredLimit, true)
                : null,
            'games' => $this->getGames(),
            'popularGames' => $this->getPopularGames(),
            'featuredTournaments' => $featuredTournaments,
            'hasMoreFeatured' => $usesV2Discovery
                ? (! $usesGroupedDiscovery && $featuredTournaments->total() > $featuredTournaments->count())
                : $this->featuredTournamentCount() > $this->featuredLimit,
            'platforms' => $this->getPlatforms(),
            'listingType' => $isHeadToHeadListing ? 'head_to_head' : 'tournament',
            'allowCompetitionSwitch' => true,
            'publicView' => true,
        ])->layout('components.layouts.app', ['title' => 'Tournaments | GamersRival']);
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
