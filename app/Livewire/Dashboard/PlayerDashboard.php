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

        $earnings = $user->wallet ? (float) $user->wallet->ledgerEntries()->where('type', LedgerType::PRIZE->value)->sum('amount') : 0.00;

        return view('livewire.dashboard.player-dashboard', [
            'user' => $user,
            'activeTournaments' => $activeTournaments,
            'recentMatches' => $recentMatches,
            'earnings' => $earnings,
        ])->layout('components.layouts.dashboard', [
            'title' => 'Gamer Terminal | PlayerSaloons',
            'dashboard_title' => 'DASHBOARD',
        ]);
    }
}
