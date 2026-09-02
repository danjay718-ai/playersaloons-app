<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\Tournament\Services\V2TournamentDiscoveryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Platform-managed 1v1 discovery.
 *
 * This deliberately uses the V2 occurrence read model rather than the older
 * player-wager H2H module. Both products can therefore coexist safely.
 */
final class PlatformHeadToHeadList extends Component
{
    use TournamentListTrait;

    /**
     * Keeps a visitor who entered through the public navigation in the public
     * shell, even when that visitor happens to have an active player session.
     */
    #[Url(as: 'view')]
    public string $viewMode = '';

    public function mount(): void
    {
        $this->competitionType = 'head_to_head';
    }

    public function render(V2TournamentDiscoveryService $discovery)
    {
        $usesV2Discovery = (bool) config('features.tournament_v2.enabled');
        $isPublicView = $this->viewMode === 'guest';
        $layout = Auth::check() && ! $isPublicView ? 'components.layouts.dashboard' : 'components.layouts.app';

        return view('livewire.tournament.platform-head-to-head-list', [
            'tournaments' => $usesV2Discovery ? null : $this->getTournamentQuery()->paginate(12),
            'tournamentGroups' => $usesV2Discovery ? $discovery->paginate($this->activeTab, $this->discoveryFilters()) : null,
            'featuredGroups' => $usesV2Discovery ? $discovery->paginate('upcoming', $this->discoveryFilters(), $this->featuredLimit, true) : null,
            'games' => $this->getGames(),
            'popularGames' => $this->getPopularGames(),
            'featuredTournaments' => $usesV2Discovery ? collect() : $this->getFeaturedTournaments(),
            'hasMoreFeatured' => ! $usesV2Discovery && $this->featuredTournamentCount() > $this->featuredLimit,
            'platforms' => $this->getPlatforms(),
            'publicView' => $isPublicView,
        ])->layout($layout, [
            'title' => 'Head-to-Head | PlayerSaloons',
            'dashboard_title' => 'HEAD-TO-HEAD',
        ]);
    }

    /** @return array<string, string> */
    private function discoveryFilters(): array
    {
        return [
            'search' => $this->search,
            'game_id' => $this->gameId,
            'frequency' => $this->frequency,
            'competition_type' => 'head_to_head',
            'platform_id' => $this->platformId,
            'team_format' => 'solo',
        ];
    }
}
