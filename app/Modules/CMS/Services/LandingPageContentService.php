<?php

declare(strict_types=1);

namespace App\Modules\CMS\Services;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\LandingSection;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Shared\Enums\HeadToHeadMatchStatus;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LandingPageContentService
{
    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $sections = LandingSection::query()
            ->where('is_active', true)
            ->with(['activeItems'])
            ->orderBy('sort_order')
            ->get()
            ->keyBy('key');

        return [
            'sections' => $sections,
            'games' => $this->activeGames(),
            'stats' => $this->stats($sections->get('stats')?->activeItems ?? collect()),
            'topPlayers' => $this->topPlayers(),
        ];
    }

    /**
     * @return Collection<int, Game>
     */
    private function activeGames(): Collection
    {
        return Game::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('slug')
            ->limit(8)
            ->get();
    }

    /**
     * @param  Collection<int, \App\Modules\CMS\Models\LandingSectionItem>  $items
     * @return array<int, array<string, string>>
     */
    private function stats(Collection $items): array
    {
        $values = [
            'matches_played' => (string) (
                GameMatch::query()->whereIn('status', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value])->count()
                + HeadToHeadMatch::query()->where('status', HeadToHeadMatchStatus::COMPLETED->value)->count()
            ),
            'winnings_paid' => '$'.number_format((float) LedgerEntry::query()
                ->whereIn('type', [LedgerType::PRIZE->value, LedgerType::H2H_PAYOUT->value])
                ->sum('amount'), 2),
            'active_players' => (string) DB::table('users')
                ->where('status', UserStatus::ACTIVE->value)
                ->count(),
            'active_games' => (string) Game::query()
                ->where('is_active', true)
                ->count(),
        ];

        return $items
            ->map(fn ($item): array => [
                'key' => (string) $item->item_key,
                'label' => (string) ($item->label ?: $item->title),
                'value' => $values[(string) $item->item_key] ?? '0',
                'icon' => (string) ($item->icon ?: 'activity'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function topPlayers(): array
    {
        $scoreboard = [];

        $matches = GameMatch::query()
            ->whereIn('status', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value])
            ->whereNotNull('winner_registration_id')
            ->where('updated_at', '>=', now()->subWeek())
            ->with(['playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration.user'])
            ->latest('updated_at')
            ->limit(300)
            ->get();

        foreach ($matches as $match) {
            $registrations = collect([$match->playerARegistration, $match->playerBRegistration])->filter();

            foreach ($registrations as $registration) {
                $user = $registration->user;

                if (! $user) {
                    continue;
                }

                $scoreboard[$user->id] ??= [
                    'name' => $user->username,
                    'avatar' => strtoupper(substr($user->username, 0, 2)),
                    'wins' => 0,
                    'losses' => 0,
                    'prize_total' => 0.0,
                ];

                if ($registration->id === $match->winner_registration_id) {
                    $scoreboard[$user->id]['wins']++;
                } else {
                    $scoreboard[$user->id]['losses']++;
                }
            }
        }

        if ($scoreboard === []) {
            return [];
        }

        $prizeTotals = LedgerEntry::query()
            ->join('wallets', 'ledger_entries.wallet_id', '=', 'wallets.id')
            ->selectRaw('wallets.user_id, SUM(ledger_entries.amount) as total_prizes')
            ->whereIn('wallets.user_id', array_keys($scoreboard))
            ->whereIn('ledger_entries.type', [LedgerType::PRIZE->value, LedgerType::H2H_PAYOUT->value])
            ->where('ledger_entries.created_at', '>=', now()->subWeek())
            ->groupBy('wallets.user_id')
            ->pluck('total_prizes', 'wallets.user_id');

        foreach ($scoreboard as $userId => $row) {
            $scoreboard[$userId]['prize_total'] = (float) ($prizeTotals[$userId] ?? 0);
        }

        return collect($scoreboard)
            ->sort(fn (array $left, array $right): int => [$right['wins'], $right['prize_total'], $left['losses']]
                <=> [$left['wins'], $left['prize_total'], $right['losses']])
            ->values()
            ->take(3)
            ->map(function (array $row, int $index): array {
                $matchesPlayed = $row['wins'] + $row['losses'];
                $winRate = $matchesPlayed > 0 ? round(($row['wins'] / $matchesPlayed) * 100) : 0;

                return [
                    'rank' => $index + 1,
                    'name' => $row['name'],
                    'avatar' => $row['avatar'],
                    'wins' => $row['wins'],
                    'winrate' => $winRate.'%',
                    'cash' => '$'.number_format($row['prize_total'], 2),
                ];
            })
            ->all();
    }
}
