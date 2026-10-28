<?php

declare(strict_types=1);

namespace App\Modules\CMS\Services;

use App\Modules\CMS\Models\Game;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Models\PrizeDistribution;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Support\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

/** @phpstan-type GuestWin array{key: string, text: string, name: string, action: string, context: string, reward: string|null, url: string, at: Carbon} */
class GuestCompetitionService
{
    /** @return Builder<Tournament> */
    private function publicTournaments(): Builder
    {
        return Tournament::query()
            ->whereHas('game', fn (Builder $games) => $games->where('games.is_active', true)->whereNull('games.deleted_at'))
            ->when(config('features.tournament_v2.enabled'),
                fn (Builder $query) => $query->where('workflow_version', 2)->whereNotNull('template_id'),
                fn (Builder $query) => $query->where('workflow_version', 1));
    }

    /** @return array{total: int, games: list<array{name: string, count: int, url: string, image: string|null}>} */
    public function openSummary(): array
    {
        $counts = $this->publicTournaments()
            ->where('competition_type', 'tournament')
            ->where('status', TournamentStatus::REGISTRATION_OPEN)
            ->where('start_at', '>', now())
            ->selectRaw('game_id, COUNT(*) as open_count')
            ->groupBy('game_id')
            ->pluck('open_count', 'game_id');

        $games = Game::query()->whereIn('id', $counts->keys())->with('translations')->get()
            ->map(fn (Game $game): array => [
                'name' => $game->localizedName(),
                'count' => (int) $counts[$game->id],
                'url' => route('games.show', $game),
                'image' => $game->bannerUrl(),
            ])->sortByDesc('count')->values()->all();

        return ['total' => array_sum(array_column($games, 'count')), 'games' => $games];
    }

    /** @return Collection<int, Tournament> */
    public function activeTournaments(): Collection
    {
        return $this->publicTournaments()
            ->where('competition_type', 'tournament')
            ->where(function (Builder $query): void {
                $query->whereIn('status', [TournamentStatus::ONGOING, TournamentStatus::CHECKIN_OPEN,
                    TournamentStatus::CHECKIN_CLOSED, TournamentStatus::REGISTRATION_CLOSED, TournamentStatus::BRACKET_GENERATED])
                    ->orWhere(fn (Builder $upcoming) => $upcoming->where('status', TournamentStatus::REGISTRATION_OPEN)->where('start_at', '>', now()));
            })
            ->with('game.translations')
            ->withCount(['registrations' => fn (Builder $query) => $query->whereNotIn('status', [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED])])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [TournamentStatus::ONGOING->value])
            ->orderBy('start_at')
            ->limit(4)->get();
    }

    /** @return Collection<int, Tournament> */
    public function registrationTournaments(): Collection
    {
        return $this->publicTournaments()
            ->where('competition_type', 'tournament')
            ->where('status', TournamentStatus::REGISTRATION_OPEN)
            ->where('start_at', '>', now())
            ->with('game.translations')
            ->withCount(['registrations' => fn (Builder $query) => $query->whereNotIn('status', [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED])])
            ->orderBy('start_at')->orderBy('id')->limit(8)->get();
    }

    /** @return array{matches_won: int, total_won: string} */
    public function winSummary(): array
    {
        $publicTournamentIds = $this->publicTournaments()
            ->whereIn('status', [TournamentStatus::ONGOING, TournamentStatus::COMPLETED])
            ->select('tournaments.id');

        $matchesWon = GameMatch::query()
            ->whereIn('tournament_id', $publicTournamentIds)
            ->where('status', MatchStatus::COMPLETED)
            ->whereNotNull('completed_at')
            ->whereHas('winnerRegistration.user', fn (Builder $users) => $users->whereNull('users.deleted_at'))
            ->count();

        $totalPrize = (float) PrizeDistribution::query()
            ->whereIn('tournament_id', $publicTournamentIds)
            ->where('status', 'completed')
            ->where('amount', '>', 0)
            ->sum('amount');

        return [
            'matches_won' => $matchesWon,
            'total_won' => Currency::format(number_format($totalPrize, 2, '.', '')),
        ];
    }

    /** @return list<GuestWin> */
    public function activity(): array
    {
        $matches = GameMatch::query()
            ->whereIn('tournament_id', $this->publicTournaments()->whereIn('status', [TournamentStatus::ONGOING, TournamentStatus::COMPLETED])->select('tournaments.id'))
            ->where('status', MatchStatus::COMPLETED)
            ->whereNotNull('completed_at')
            ->whereHas('winnerRegistration.user', fn (Builder $users) => $users->whereNull('users.deleted_at'))
            ->with(['winnerRegistration.user', 'round', 'tournament.rounds'])
            ->latest('completed_at')->orderByDesc('id')->limit(8)->get();

        $prizes = PrizeDistribution::query()
            ->whereIn('tournament_id', $matches->pluck('tournament_id'))
            ->where('status', 'completed')->where('rank', 1)->where('amount', '>', 0)
            ->with('wallet')->get()
            ->keyBy(fn (PrizeDistribution $prize): string => $prize->tournament_id.':'.$prize->wallet->user_id);

        return $matches->map(fn (GameMatch $match): array => $this->matchWin(
            $match,
            $prizes->get($match->tournament_id.':'.$match->winnerRegistration->user_id),
        ))->values()->all();
    }

    /** @return GuestWin */
    private function matchWin(GameMatch $match, ?PrizeDistribution $prize): array
    {
        // Only the champion's final can carry the settled tournament prize, never an earlier-round win.
        $reward = $prize !== null && $match->tournament->status === TournamentStatus::COMPLETED
            && $match->round->round_number === $match->tournament->rounds->max('round_number')
            ? '+'.Currency::format($prize->amount) : null;

        return [
            'key' => 'match-'.$match->id,
            'text' => Lang::string(':player won a match in :tournament', ['player' => $match->winnerRegistration->user->username, 'tournament' => $match->tournament->name]),
            'name' => $match->winnerRegistration->user->username,
            'action' => Lang::string('won in'),
            'context' => $match->tournament->name,
            'reward' => $reward,
            'url' => route('tournaments.view', $match->tournament->uuid),
            'at' => $match->completed_at ?? $match->updated_at ?? now(),
        ];
    }
}
