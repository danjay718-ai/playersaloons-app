<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Modules\CMS\Models\Game;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PlayerDashboard extends Component
{
    // Bound to the 'tab' query parameter
    public string $tab = 'overview';

    // Dashboard Tournament filters and sub-tabs
    public string $tSearch = '';

    public string $tGameId = '';

    public string $tStatus = '';

    public string $tFrequency = 'daily'; // Default is daily

    public string $tSubTab = 'my_tournaments'; // Default is my_tournaments

    // Chat properties
    public string $chatMessage = '';

    public array $messages = [];

    // Head-to-Head properties
    public float $stakeAmount = 10.00;

    public string $selectedGame = 'Valorant';

    public array $challenges = [];

    public bool $isSearching = false;

    public ?array $matchedOpponent = null;

    protected $queryString = [
        'tab' => ['except' => 'overview'],
        'tSubTab' => ['except' => 'my_tournaments'],
    ];

    public function mount()
    {
        $user = Auth::user();
        if ($user) {
            $adminRoles = ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'FINANCE_OPERATOR', 'KYC_REVIEWER', 'SUPPORT_AGENT', 'TOURNAMENT_ORGANIZER'];
            if ($user->hasAnyRole($adminRoles)) {
                return redirect()->to('/admin');
            }
        }

        $tabQuery = request()->query('tab', 'overview');
        if (in_array($tabQuery, ['overview', 'tournaments', 'head-to-head', 'leaderboards', 'streams', 'chat'])) {
            $this->tab = $tabQuery;
        }

        $subTabQuery = request()->query('tSubTab', 'my_tournaments');
        if (in_array($subTabQuery, ['my_tournaments', 'browse_tournaments'])) {
            $this->tSubTab = $subTabQuery;
        }

        // Initialize mock chat messages
        $this->messages = [
            ['username' => 'NeonSpecter', 'text' => 'Who wants to duel in FIFA? Stake is $10.', 'time' => '12:04', 'avatar' => 'NS'],
            ['username' => 'ViperZero', 'text' => 'I\'m down, invite me!', 'time' => '12:05', 'avatar' => 'VZ'],
            ['username' => 'GamerGod', 'text' => 'Valorant tournament is stacked today, good luck to everyone!', 'time' => '12:06', 'avatar' => 'GG'],
            ['username' => 'HyperDrift', 'text' => 'Let\'s goooo! Streaming the match soon.', 'time' => '12:08', 'avatar' => 'HD'],
        ];

        // Initialize mock H2H challenges
        $this->challenges = [
            ['id' => 1, 'username' => 'ShadowBlade', 'game' => 'CS2', 'stake' => 15.00, 'avatar' => 'SB', 'status' => 'waiting'],
            ['id' => 2, 'username' => 'AlphaKnight', 'game' => 'FIFA 24', 'stake' => 25.00, 'avatar' => 'AK', 'status' => 'waiting'],
            ['id' => 3, 'username' => 'CyberPunk', 'game' => 'Tekken 8', 'stake' => 50.00, 'avatar' => 'CP', 'status' => 'waiting'],
        ];
    }

    public function sendMessage(): void
    {
        if (trim($this->chatMessage) === '') {
            return;
        }

        $user = Auth::user();

        $this->messages[] = [
            'username' => $user->username,
            'text' => $this->chatMessage,
            'time' => now()->format('H:i'),
            'avatar' => strtoupper(substr($user->username, 0, 2)),
        ];

        $this->chatMessage = '';
        $this->dispatch('chat-updated');

        $botNames = ['NeonSpecter', 'ViperZero', 'GamerGod', 'HyperDrift', 'SaloonsBot'];
        $botAnswers = [
            'Nice shot! Anyone wants to play next?',
            'Good luck with that match!',
            'Let\'s check the leaderboards, top players are insane.',
            'GG! Add me on Discord later.',
            'System alert: New tournaments will start in 1 hour!',
        ];

        $randomBot = $botNames[array_rand($botNames)];
        $randomReply = $botAnswers[array_rand($botAnswers)];

        $this->messages[] = [
            'username' => $randomBot,
            'text' => $randomReply,
            'time' => now()->format('H:i'),
            'avatar' => strtoupper(substr($randomBot, 0, 2)),
        ];
    }

    public function createChallenge(): void
    {
        $user = Auth::user();
        if ($this->stakeAmount <= 0) {
            return;
        }

        $this->challenges[] = [
            'id' => count($this->challenges) + 1,
            'username' => $user->username,
            'game' => $this->selectedGame,
            'stake' => $this->stakeAmount,
            'avatar' => strtoupper(substr($user->username, 0, 2)),
            'status' => 'waiting',
        ];
    }

    public function findDuel(): void
    {
        $this->isSearching = true;
        $this->matchedOpponent = null;
    }

    public function cancelSearch(): void
    {
        $this->isSearching = false;
        $this->matchedOpponent = null;
    }

    public function simulateMatchFound(): void
    {
        $this->isSearching = false;
        $opponents = [
            ['username' => 'ViperZero', 'level' => 45, 'winrate' => '68%', 'game' => 'Valorant', 'avatar' => 'VZ'],
            ['username' => 'ShadowBlade', 'level' => 52, 'winrate' => '71%', 'game' => 'CS2', 'avatar' => 'SB'],
            ['username' => 'NeonSpecter', 'level' => 38, 'winrate' => '62%', 'game' => 'FIFA 24', 'avatar' => 'NS'],
        ];

        $this->matchedOpponent = $opponents[array_rand($opponents)];
    }

    /**
     * Render the dashboard view.
     * Implements lazy-loading based on active tab to optimize performance.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/login');
        }

        // Initialize empty containers
        $activeMatches = [];
        $activeTournaments = [];
        $closedTournaments = [];
        $browseTournaments = [];
        $playerStats = [
            'total_matches' => 0,
            'wins' => 0,
            'losses' => 0,
            'win_rate' => 0.0,
            'earnings' => 0.00,
            'ranking' => 0, // TODO: Ranking system not implemented
            'xp' => 0,      // TODO: XP system not implemented
            'xp_next' => 0, // TODO: XP system not implemented
            'streak' => 0,  // TODO: Streak system not implemented
        ];
        $games = [];

        // ── LAZY LOAD: Overview Tab Data ─────────────────────────────────────
        if ($this->tab === 'overview') {
            $userRegistrationIds = TournamentRegistration::query()
                ->where('user_id', $user->id)
                ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
                ->pluck('id');

            // 1. Compute real match stats
            $allUserMatches = GameMatch::query()
                ->where(function ($q) use ($userRegistrationIds) {
                    $q->whereIn('player_a_registration_id', $userRegistrationIds)
                        ->orWhereIn('player_b_registration_id', $userRegistrationIds);
                })
                ->whereIn('status', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value])
                ->with(['winnerRegistration'])
                ->get();

            $totalMatches = $allUserMatches->count();
            $wins = $allUserMatches->filter(function ($match) use ($userRegistrationIds) {
                return $match->winnerRegistration !== null
                    && $userRegistrationIds->contains($match->winner_registration_id);
            })->count();

            $playerStats['total_matches'] = $totalMatches;
            $playerStats['wins'] = $wins;
            $playerStats['losses'] = $totalMatches - $wins;
            $playerStats['win_rate'] = $totalMatches > 0 ? round(($wins / $totalMatches) * 100, 1) : 0.0;

            // 2. Earnings
            try {
                if ($user->wallet) {
                    $playerStats['earnings'] = (float) $user->wallet
                        ->ledgerEntries()
                        ->where('type', LedgerType::PRIZE->value)
                        ->sum('amount');
                }
            } catch (\Throwable) {
                $playerStats['earnings'] = 0.00;
            }

            // 3. Recent matches (Battle Log)
            $activeMatches = GameMatch::query()
                ->where(function ($q) use ($userRegistrationIds) {
                    $q->whereIn('player_a_registration_id', $userRegistrationIds)
                        ->orWhereIn('player_b_registration_id', $userRegistrationIds);
                })
                ->with(['tournament', 'round', 'playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration.user'])
                ->orderBy('updated_at', 'desc')
                ->take(5)
                ->get();
        }

        // ── LAZY LOAD: Tournaments Tab Data ──────────────────────────────────
        if ($this->tab === 'tournaments') {
            // 1. User Tournaments
            $activeTournaments = Tournament::query()
                ->whereHas('registrations', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
                })
                ->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
                ->with('game.translations')
                ->get();

            $closedTournaments = Tournament::query()
                ->whereHas('registrations', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
                })
                ->whereIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
                ->with('game.translations')
                ->get();

            // 2. Browse tournaments
            $browseQuery = Tournament::query()
                ->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
                ->with(['game.translations', 'registrations']);

            if ($this->tSearch) {
                $browseQuery->where('name', 'like', '%'.$this->tSearch.'%');
            }
            if ($this->tGameId) {
                $browseQuery->where('game_id', $this->tGameId);
            }
            if ($this->tStatus) {
                $browseQuery->where('status', $this->tStatus);
            }
            if ($this->tFrequency) {
                $browseQuery->where('frequency', $this->tFrequency);
            }

            $browseTournaments = $browseQuery->orderBy('created_at', 'desc')->get();

            $games = Game::query()
                ->with('translations')
                ->where('is_active', true)
                ->get();
        }

        $titles = [
            'overview' => 'DASHBOARD',
            'tournaments' => 'TOURNAMENTS HUB',
            'head-to-head' => 'HEAD-TO-HEAD DUELS',
            'leaderboards' => 'GLOBAL LEADERBOARDS',
            'streams' => 'LIVE BROADCASTS',
            'chat' => 'GLOBAL COMMUNICATIONS',
        ];

        return view('livewire.dashboard.player-dashboard', [
            'user' => $user,
            'activeMatches' => $activeMatches,
            'activeTournaments' => $activeTournaments,
            'closedTournaments' => $closedTournaments,
            'browseTournaments' => $browseTournaments,
            'playerStats' => $playerStats,
            'games' => $games,
        ])->layout('components.layouts.dashboard', [
            'title' => 'Gamer Terminal | PlayerSaloons',
            'dashboard_title' => $titles[$this->tab] ?? 'DASHBOARD',
        ]);
    }
}
