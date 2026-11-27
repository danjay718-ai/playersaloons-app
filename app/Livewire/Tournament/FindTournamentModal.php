<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\ExternalProviderAccount;
use App\Modules\Tournament\Services\FindActiveTournamentsService;
use App\Modules\Tournament\Services\TournamentTimezone;
use App\Shared\Support\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class FindTournamentModal extends Component
{
    public bool $open = false;

    public string $search = '';

    public string $category = '';

    public string $gameId = '';

    public string $platformId = '';

    public string $budget = '';

    public string $timeWindow = '';

    public string $competitionType = '';

    #[On('open-find-tournament')]
    public function openModal(string $category = '', string $gameId = ''): void
    {
        if (trim($category) !== '') {
            $this->category = trim($category);
        }

        if (trim($gameId) !== '') {
            $this->gameId = trim($gameId);
        }

        $this->open = true;
    }

    public function closeModal(): void
    {
        $this->open = false;
    }

    public function updatedCategory(): void
    {
        $this->category = trim($this->category);
        if ($this->gameId === '') {
            return;
        }

        $compatible = Game::query()
            ->availableInCatalog()
            ->inCategorySlug($this->category)
            ->whereKey($this->gameId)
            ->exists();

        if (! $compatible) {
            $this->gameId = '';
        }
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->category = '';
        $this->gameId = '';
        $this->platformId = '';
        $this->budget = '';
        $this->timeWindow = '';
        $this->competitionType = '';
    }

    public function hasActiveFilters(): bool
    {
        return trim($this->search) !== ''
            || trim($this->category) !== ''
            || trim($this->gameId) !== ''
            || trim($this->platformId) !== ''
            || trim($this->budget) !== ''
            || trim($this->timeWindow) !== ''
            || trim($this->competitionType) !== '';
    }

    public function render(FindActiveTournamentsService $finder): View
    {
        // Strictly lazy: zero DB queries while modal is closed.
        if (! $this->open) {
            return view('livewire.tournament.find-tournament-modal', [
                'categories' => [],
                'games' => [],
                'platforms' => [],
                'results' => collect(),
                'exact' => true,
                'fallbackReason' => null,
                'totalMatching' => 0,
                'hasActiveFilters' => false,
                'hasPlayStrategyLinked' => false,
                'isGuest' => ! Auth::check(),
                'currencySymbol' => '',
                'arenaTimezone' => 'UTC',
            ]);
        }

        $options = $finder->filterOptions($this->category);
        $hasActiveFilters = $this->hasActiveFilters();
        $found = $hasActiveFilters
            ? $finder->find([
                'search' => $this->search,
                'category' => $this->category,
                'game_id' => $this->gameId,
                'platform_id' => $this->platformId,
                'budget' => $this->budget,
                'time_window' => $this->timeWindow,
                'competition_type' => $this->competitionType,
            ], 8)
            : [
                'tournaments' => collect(),
                'exact' => true,
                'fallback_reason' => null,
                'total_matching' => 0,
            ];

        $userId = Auth::id();
        $hasPlayStrategyLinked = $hasActiveFilters
            && $userId !== null
            && ExternalProviderAccount::query()
                ->where('user_id', $userId)
                ->where('provider', ExternalProviderAccount::PROVIDER_PLAYSTRATEGY)
                ->exists();

        return view('livewire.tournament.find-tournament-modal', [
            'categories' => $options['categories'],
            'games' => $options['games'],
            'platforms' => $options['platforms'],
            'results' => $found['tournaments'],
            'exact' => $found['exact'],
            'fallbackReason' => $found['fallback_reason'],
            'totalMatching' => $found['total_matching'],
            'hasActiveFilters' => $hasActiveFilters,
            'hasPlayStrategyLinked' => $hasPlayStrategyLinked,
            'isGuest' => $userId === null,
            'currencySymbol' => Currency::symbol(),
            'arenaTimezone' => app(TournamentTimezone::class)->value(),
        ]);
    }
}
