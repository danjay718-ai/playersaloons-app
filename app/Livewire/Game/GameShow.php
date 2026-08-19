<?php

declare(strict_types=1);

namespace App\Livewire\Game;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class GameShow extends Component
{
    use WithPagination;

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

    public function render()
    {
        $platforms = $this->game->platforms()->where('platforms.is_active', true)->orderBy('platforms.name')->get();
        if ($platforms->isEmpty()) {
            $platforms = Platform::query()->where('is_active', true)->orderBy('name')->get();
        }

        $featured = $this->baseTournamentQuery()
            ->where('is_featured', true)
            ->whereIn('status', array_merge($this->statuses('upcoming'), $this->statuses('ongoing')))
            ->orderBy('start_at')
            ->limit(4)
            ->get();

        $tournaments = $this->filteredTournamentQuery()
            ->paginate($this->activeTab === 'browse' ? 12 : 6);

        $streams = StreamChannel::query()
            ->with(['user.profile', 'tournament'])
            ->where(function ($query): void {
                $query->where('game_id', $this->game->getKey())
                    ->orWhereHas('tournament', fn ($tournament) => $tournament->where('game_id', $this->game->getKey()));
            })
            ->where('is_public', true)
            ->whereNull('taken_down_at')
            ->orderByDesc('is_live')
            ->orderByDesc('viewer_count')
            ->limit(12)
            ->get();

        $view = view('livewire.game.game-show', [
            'featuredTournaments' => $featured,
            'tournaments' => $tournaments,
            'streams' => $streams,
            'platforms' => $platforms,
        ]);

        return Auth::check()
            ? $view->layout('components.layouts.dashboard', ['title' => $this->game->localizedName().' | PlayerSaloons', 'dashboard_title' => 'GAME HUB'])
            : $view->layout('components.layouts.app', ['title' => $this->game->localizedName().' | PlayerSaloons']);
    }

    private function filteredTournamentQuery()
    {
        return $this->baseTournamentQuery()
            ->whereIn('status', $this->statuses($this->tournamentStatus))
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->startDate !== '', fn ($query) => $query->whereDate('start_at', '>=', $this->startDate))
            ->when($this->platformId !== '', fn ($query) => $query->where('platform_id', $this->platformId))
            ->when($this->competitionType !== '', fn ($query) => $query->where('competition_type', $this->competitionType))
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
