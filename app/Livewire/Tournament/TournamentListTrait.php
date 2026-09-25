<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RegistrationStatus;
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
    public string $activeTab = 'upcoming';

    #[Url]
    public string $competitionType = '';

    #[Url]
    public string $frequency = '';

    #[Url]
    public string $platformId = '';

    #[Url]
    public string $maxTeams = '';

    #[Url]
    public string $customMaxTeams = '';

    public string $gameSearch = '';

    public int $featuredLimit = 4;

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

    public function updatingCompetitionType(): void
    {
        $this->resetPage();
    }

    public function updatedFrequency(): void
    {
        $this->resetPage();
    }

    public function updatedPlatformId(): void
    {
        $this->resetPage();
    }

    public function updatedMaxTeams(): void
    {
        $this->resetPage();
    }

    public function updatedCustomMaxTeams(): void
    {
        $this->resetPage();
    }

    public function loadMoreFeatured(): void
    {
        $this->featuredLimit += 4;
    }

    protected function getTournamentQuery()
    {
        $query = Tournament::query()
            ->whereHas('game', fn ($games) => $games->availableInCatalog())
            ->with(['game.translations', 'platform'])
            ->withCount(['registrations' => function ($q) {
                $q->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            }])
            ->whereIn('status', $this->statusesForTab($this->activeTab));
        $this->applyWorkflowFlag($query);

        if ($this->search) {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        if ($this->gameId) {
            $query->where('game_id', $this->gameId);
        }

        if ($this->frequency !== '') {
            $query->where('frequency', $this->frequency);
        }

        if ($this->competitionType !== '') {
            $query->where('competition_type', $this->competitionType);
        }

        if ($this->platformId !== '') {
            $query->forPlatform((int) $this->platformId);
        }

        if ($this->competitionType !== 'head_to_head' && ($maxTeams = $this->selectedMaxTeams()) !== null) {
            $query->where('max_participants', $maxTeams);
        }

        return $this->activeTab === 'past'
            ? $query->orderByDesc('completed_at')
            : $query->orderBy('start_at');
    }

    protected function getGames()
    {
        return Game::query()->with('translations')->where('is_active', true)->get();
    }

    protected function getPopularGames()
    {
        $activeStatuses = array_merge($this->statusesForTab('upcoming'), $this->statusesForTab('ongoing'));

        return Game::query()
            ->with('translations')
            ->withCount(['tournaments as active_tournaments_count' => fn ($query) => $query
                ->whereIn('status', $activeStatuses)
                ->when(! config('features.tournament_v2.enabled'), fn ($tournaments) => $tournaments->where('workflow_version', 1))])
            ->where('is_active', true)
            ->when($this->gameSearch !== '', function ($query): void {
                $term = '%'.$this->gameSearch.'%';
                $query->where(function ($games) use ($term): void {
                    $games->where('slug', 'like', $term)
                        ->orWhereHas('translations', fn ($translations) => $translations->where('name', 'like', $term));
                });
            })
            ->orderByDesc('active_tournaments_count')
            ->orderBy('slug')
            ->get();
    }

    protected function getFeaturedTournaments()
    {
        $query = Tournament::query()
            ->whereHas('game', fn ($games) => $games->availableInCatalog())
            ->with(['game.translations', 'platform'])
            ->withCount(['registrations' => fn ($query) => $query->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])])
            ->where('is_featured', true)
            ->whereIn('status', array_merge($this->statusesForTab('upcoming'), $this->statusesForTab('ongoing')))
            ->when($this->frequency !== '', fn ($query) => $query->where('frequency', $this->frequency))
            ->orderBy('start_at')
            ->limit($this->featuredLimit);
        $this->applyWorkflowFlag($query);

        return $query->get();
    }

    protected function featuredTournamentCount(): int
    {
        $query = Tournament::query()
            ->whereHas('game', fn ($games) => $games->availableInCatalog())
            ->where('is_featured', true)
            ->whereIn('status', array_merge($this->statusesForTab('upcoming'), $this->statusesForTab('ongoing')))
            ->when($this->frequency !== '', fn ($query) => $query->where('frequency', $this->frequency));
        $this->applyWorkflowFlag($query);

        return $query->count();
    }

    protected function getPlatforms()
    {
        return Platform::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** @return array<int, string> */
    protected function statusesForTab(string $tab): array
    {
        return match ($tab) {
            'ongoing' => [
                TournamentStatus::REGISTRATION_CLOSED->value,
                TournamentStatus::CHECKIN_OPEN->value,
                TournamentStatus::CHECKIN_CLOSED->value,
                TournamentStatus::BRACKET_GENERATED->value,
                TournamentStatus::ONGOING->value,
            ],
            'past' => [TournamentStatus::COMPLETED->value],
            default => [TournamentStatus::REGISTRATION_OPEN->value],
        };
    }

    private function applyWorkflowFlag($query): void
    {
        if (! config('features.tournament_v2.enabled')) {
            $query->where('workflow_version', 1);
        }
    }

    protected function selectedMaxTeams(): ?int
    {
        $value = $this->maxTeams === 'custom' ? $this->customMaxTeams : $this->maxTeams;
        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $maximum = (int) $value;

        return $maximum >= 2 && $maximum <= 128 && $maximum % 2 === 0 ? $maximum : null;
    }
}
