<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Modules\Community\Models\BroadcastMessage;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PlayerDashboard extends Component
{
    private const PLAYER_NAV_ITEMS = [
        ['label' => 'Overview', 'url' => '/dashboard', 'pattern' => 'dashboard'],
        ['label' => 'My Tournaments', 'url' => '/my-tournaments', 'pattern' => 'my-tournaments'],
        ['label' => 'Browse', 'url' => '/tournaments/browse', 'pattern' => 'tournaments/browse*'],
        ['label' => 'H2H Duels', 'url' => '/head-to-head', 'pattern' => 'head-to-head'],
        ['label' => 'Leaderboard', 'url' => '/leaderboards', 'pattern' => 'leaderboards'],
        ['label' => 'Streams', 'url' => '/streams', 'pattern' => 'streams'],
        ['label' => 'Chat', 'url' => '/chat', 'pattern' => 'chat'],
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
    }

    public function render()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/login');
        }

        // Summary Data for Cockpit
        $activeTournaments = Tournament::query()
            ->whereHas('registrations', fn($q) => $q->where('user_id', $user->id))
            ->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
            ->with('game.translations')
            ->withCount(['registrations' => function ($q) {
                $q->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            }])
            ->orderBy('start_at', 'asc')
            ->take(3)
            ->get();

        $recentMatches = GameMatch::query()
            ->where(function ($q) use ($user) {
                $q->whereHas('playerARegistration', fn($qr) => $qr->where('user_id', $user->id))
                  ->orWhereHas('playerBRegistration', fn($qr) => $qr->where('user_id', $user->id));
            })
            ->with('tournament')
            ->orderBy('updated_at', 'desc')
            ->take(3)
            ->get();

        $wallet = $user->wallet()
            ->withSum(['ledgerEntries as prize_earnings' => function ($query) {
                $query->where('type', LedgerType::PRIZE->value);
            }], 'amount')
            ->first();

        $earnings = (float) ($wallet?->prize_earnings ?? 0.00);

        $announcements = BroadcastMessage::query()
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->latest()
            ->take(3)
            ->get();

        return view('livewire.dashboard.player-dashboard', [
            'user' => $user,
            'activeTournaments' => $activeTournaments,
            'recentMatches' => $recentMatches,
            'earnings' => $earnings,
            'announcements' => $announcements,
            'navItems' => self::PLAYER_NAV_ITEMS,
        ])->layout('components.layouts.dashboard', [
            'title' => 'Gamer Terminal | PlayerSaloons',
            'dashboard_title' => 'DASHBOARD',
        ]);
    }
}
