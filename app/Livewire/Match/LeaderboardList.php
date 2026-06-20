<?php

declare(strict_types=1);

namespace App\Livewire\Match;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\MatchStatus;
use Livewire\Component;

class LeaderboardList extends Component
{
    public function render()
    {
        return view('livewire.match.leaderboard-list', [
            'topPlayers' => $this->topPlayers(),
        ])->layout('components.layouts.dashboard', [
            'title' => 'Leaderboards | PlayerSaloons',
            'dashboard_title' => 'GLOBAL LEADERBOARDS',
        ]);
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
            ->with(['playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration.user'])
            ->latest('updated_at')
            ->limit(500)
            ->get();

        foreach ($matches as $match) {
            $registrations = collect([$match->playerARegistration, $match->playerBRegistration])->filter();

            foreach ($registrations as $registration) {
                $user = $registration->user;

                if (! $user) {
                    continue;
                }

                $scoreboard[$user->id] ??= [
                    'user_id' => $user->id,
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
            ->where('ledger_entries.type', LedgerType::PRIZE->value)
            ->groupBy('wallets.user_id')
            ->pluck('total_prizes', 'wallets.user_id');

        foreach ($scoreboard as $userId => $row) {
            $scoreboard[$userId]['prize_total'] = (float) ($prizeTotals[$userId] ?? 0);
        }

        return collect($scoreboard)
            ->sort(function (array $left, array $right) {
                return [$right['wins'], $right['prize_total'], $left['losses']]
                    <=> [$left['wins'], $left['prize_total'], $right['losses']];
            })
            ->values()
            ->take(25)
            ->map(function (array $row, int $index) {
                $matchesPlayed = $row['wins'] + $row['losses'];
                $winRate = $matchesPlayed > 0 ? round(($row['wins'] / $matchesPlayed) * 100) : 0;

                return [
                    'rank' => $index + 1,
                    'name' => $row['name'],
                    'avatar' => $row['avatar'],
                    'wins' => $row['wins'],
                    'losses' => $row['losses'],
                    'winrate' => $winRate.'%',
                    'cash' => '$'.number_format($row['prize_total'], 2),
                    'tier' => $this->tierForWins($row['wins']),
                ];
            })
            ->all();
    }

    private function tierForWins(int $wins): string
    {
        return match (true) {
            $wins >= 100 => 'Challenger',
            $wins >= 50 => 'Diamond',
            $wins >= 25 => 'Platinum',
            $wins >= 10 => 'Gold',
            default => 'Unranked',
        };
    }
}
