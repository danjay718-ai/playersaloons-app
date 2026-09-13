<?php

declare(strict_types=1);

namespace App\Livewire\Game;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\V2TournamentDiscoveryService;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class GameShow extends Component
{
    use WithPagination;

    private const PLAYER_FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public Game $game;

    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    #[Url(as: 'status')]
    public string $tournamentStatus = 'upcoming';

    #[Url]
    public string $search = '';

    #[Url]
    public string $startDate = '';

    #[Url]
    public string $platformId = '';

    #[Url]
    public string $competitionType = '';

    /** Preserve the public shell when a signed-in player came from public discovery. */
    #[Url(as: 'view')]
    public string $viewMode = '';

    #[Url]
    public string $frequency = '';

    public function mount(Game $game): void
    {
        abort_unless($game->is_active, 404);
        $this->game = $game->load('translations');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['tournamentStatus', 'search', 'startDate', 'platformId', 'competitionType', 'frequency'], true)) {
            $this->resetPage();
        }
    }

    public function render(V2TournamentDiscoveryService $discovery)
    {
        $platforms = $this->game->platforms()->where('platforms.is_active', true)->orderBy('platforms.name')->get();
        if ($platforms->isEmpty()) {
            $platforms = Platform::query()->where('is_active', true)->orderBy('name')->get();
        }

        $usesV2Discovery = (bool) config('features.tournament_v2.enabled');
        // Overview intentionally has no competition cards. Browse is the
        // single discovery surface for either tournament or H2H context.
        $featured = ! $usesV2Discovery && $this->activeTab === 'browse'
            ? $this->baseTournamentQuery()
                ->where('competition_type', $this->competitionType ?: 'tournament')
                ->when($this->frequency !== '', fn ($query) => $query->where('frequency', $this->frequency))
                ->where('is_featured', true)
                ->whereIn('status', array_merge($this->statuses('upcoming'), $this->statuses('ongoing')))
                ->orderBy('start_at')
                ->limit(4)
                ->get()
            : collect();
        $tournaments = ! $usesV2Discovery && $this->activeTab === 'browse'
            ? $this->filteredTournamentQuery()->paginate(12)
            : null;
        $tournamentGroups = $usesV2Discovery && $this->activeTab === 'browse'
            ? $discovery->paginate($this->tournamentStatus, $this->discoveryFilters(), 12)
            : null;
        $featuredGroups = $usesV2Discovery && $this->activeTab === 'browse'
            ? $discovery->paginate('upcoming', [
                'game_id' => (string) $this->game->id,
                'competition_type' => $this->competitionType ?: 'tournament',
                'frequency' => $this->frequency,
            ], 4, true)
            : null;

        $streams = StreamChannel::query()
            ->with(['user.profile', 'tournament'])
            ->where(function ($query): void {
                $query->where('game_id', $this->game->getKey())
                    ->orWhereHas('tournament', fn ($tournament) => $tournament->where('game_id', $this->game->getKey()));
            })
            ->where('is_public', true)
            ->whereNull('taken_down_at')
            // Seeded trailers are catalog/demo content, not player or
            // tournament streams. Never expose them in the Game Hub stream
            // tab, regardless of whether a database was seeded for local use.
            ->where(function ($query): void {
                $query->whereNull('metadata')
                    ->orWhereJsonDoesntContain('metadata->kind', 'sample_game_trailer');
            })
            ->orderByDesc('is_live')
            ->orderByDesc('viewer_count')
            ->limit(12)
            ->get();

        $activeCompetitionCount = $this->activeCompetitionCount();

        $view = view('livewire.game.game-show', [
            'featuredTournaments' => $featured,
            'featuredGroups' => $featuredGroups,
            'tournaments' => $tournaments,
            'tournamentGroups' => $tournamentGroups,
            'streams' => $streams,
            'platforms' => $platforms,
            'publicView' => $this->viewMode === 'guest',
            'activeCompetitionCount' => $activeCompetitionCount,
            'fixedFrequency' => $this->hasPlayerFrequencyContext(),
        ]);

        return Auth::check() && $this->viewMode !== 'guest'
            ? $view->layout('components.layouts.dashboard', ['title' => $this->game->localizedName().' | PlayerSaloons', 'dashboard_title' => 'GAME HUB'])
            : $view->layout('components.layouts.app', ['title' => $this->game->localizedName().' | PlayerSaloons']);
    }

    /** @return array<string, string> */
    private function discoveryFilters(): array
    {
        return [
            'game_id' => (string) $this->game->id,
            'search' => $this->search,
            'start_date' => $this->startDate,
            'platform_id' => $this->platformId,
            'competition_type' => $this->competitionType ?: 'tournament',
            'frequency' => $this->frequency,
        ];
    }

    private function filteredTournamentQuery()
    {
        return $this->baseTournamentQuery()
            ->whereIn('status', $this->statuses($this->tournamentStatus))
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->startDate !== '', fn ($query) => $query->whereDate('start_at', '>=', $this->startDate))
            ->when($this->platformId !== '', fn ($query) => $query->where('platform_id', $this->platformId))
            ->where('competition_type', $this->competitionType ?: 'tournament')
            ->when($this->frequency !== '', fn ($query) => $query->where('frequency', $this->frequency))
            ->when($this->tournamentStatus === 'past', fn ($query) => $query->orderByDesc('completed_at'), fn ($query) => $query->orderBy('start_at'));
    }

    private function baseTournamentQuery()
    {
        return Tournament::query()
            ->where('game_id', $this->game->getKey())
            ->with(['game.translations', 'platform'])
            ->withCount(['registrations' => fn ($query) => $query->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])]);
    }

    private function activeCompetitionCount(): int
    {
        $now = now();

        return $this->game->tournaments()
            ->where('competition_type', $this->competitionType ?: 'tournament')
            ->when($this->frequency !== '', fn ($query) => $query->where('frequency', $this->frequency))
            ->where(function ($query) use ($now): void {
                $query->where(function ($upcoming) use ($now): void {
                    $upcoming->where('status', TournamentStatus::REGISTRATION_OPEN->value)
                        ->where('start_at', '>', $now);
                })->orWhere(function ($ongoing) use ($now): void {
                    $ongoing->whereIn('status', $this->statuses('ongoing'))
                        ->where(function ($scheduled) use ($now): void {
                            $scheduled->whereNull('end_at')
                                ->orWhere('end_at', '>', $now);
                        });
                });
            })
            ->count();
    }

    private function hasPlayerFrequencyContext(): bool
    {
        return Auth::check()
            && $this->viewMode !== 'guest'
            && $this->competitionType !== 'head_to_head'
            && in_array($this->frequency, self::PLAYER_FREQUENCIES, true);
    }

    /** @return array<int, string> */
    private function statuses(string $status): array
    {
        return match ($status) {
            'ongoing' => [TournamentStatus::REGISTRATION_CLOSED->value, TournamentStatus::CHECKIN_OPEN->value, TournamentStatus::CHECKIN_CLOSED->value, TournamentStatus::BRACKET_GENERATED->value, TournamentStatus::ONGOING->value],
            'past' => [TournamentStatus::COMPLETED->value],
            default => [TournamentStatus::REGISTRATION_OPEN->value],
        };
    }
}
