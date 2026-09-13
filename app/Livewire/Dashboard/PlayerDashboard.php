<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Modules\Identity\Services\PlayerDashboardService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PlayerDashboard extends Component
{
    private const PLAYER_NAV_ITEMS = [
        ['label' => 'Overview', 'url' => '/dashboard', 'pattern' => 'dashboard'],
        ['label' => 'My Tournaments', 'url' => '/my-tournaments', 'pattern' => 'my-tournaments'],
        ['label' => 'Browse', 'url' => '/tournaments/browse', 'pattern' => 'tournaments/browse*'],
        ['label' => 'Leaderboard', 'url' => '/leaderboards', 'pattern' => 'leaderboards'],
        ['label' => 'Streams', 'url' => '/streams', 'pattern' => 'streams'],
        ['label' => 'Chat', 'url' => '/chat', 'pattern' => 'chat'],
    ];

    public function mount()
    {
        $user = Auth::user();
        if ($user?->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'FINANCE_OPERATOR', 'KYC_REVIEWER', 'SUPPORT_AGENT', 'TOURNAMENT_ORGANIZER'])) {
            return redirect()->to('/admin');
        }
    }

    public function render(PlayerDashboardService $dashboard)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/login');
        }

        $navItems = self::PLAYER_NAV_ITEMS;
        if (config('features.tournament_v2.enabled')) {
            $navItems[] = ['label' => 'Head-to-Head', 'url' => '/h2h', 'pattern' => 'h2h'];
        }
        if (config('features.player_wager.enabled')) {
            $navItems[] = ['label' => 'Head-to-Head Duels', 'url' => '/head-to-head', 'pattern' => 'head-to-head'];
        }

        return view('livewire.dashboard.player-dashboard', array_merge(
            $dashboard->dataFor($user),
            [
                'user' => $user,
                'navItems' => $navItems,
            ],
        ))->layout('components.layouts.dashboard', [
            'title' => 'Gamer Terminal | PlayerSaloons',
            'dashboard_title' => 'PLAYER COMMAND CENTER',
        ]);
    }
}
