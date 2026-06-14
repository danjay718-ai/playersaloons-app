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
    }

    /**
     * Render the dashboard overview.
     */
    public function render()
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->to('/login');
        }

        $userRegistrationIds = TournamentRegistration::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
            ->pluck('id');

        // Stats (Matches/Earnings)
        $allUserMatches = GameMatch::query()
            ->where(function ($q) use ($userRegistrationIds) {
                $q->whereIn('player_a_registration_id', $userRegistrationIds)
                    ->orWhereIn('player_b_registration_id', $userRegistrationIds);
            })
            ->whereIn('status', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value])
            ->get();

        $totalMatches = $allUserMatches->count();
        $wins = $allUserMatches->filter(fn($m) => $userRegistrationIds->contains($m->winner_registration_id))->count();

        $playerStats = [
            'total_matches' => $totalMatches,
            'wins' => $wins,
            'losses' => $totalMatches - $wins,
            'earnings' => $user->wallet ? (float) $user->wallet->ledgerEntries()->where('type', LedgerType::PRIZE->value)->sum('amount') : 0.00,
        ];

        $activeTournaments = Tournament::query()
            ->whereHas('registrations', fn($q) => $q->where('user_id', $user->id)->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]))
            ->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
            ->get();

        return view('livewire.dashboard.player-dashboard', [
            'user' => $user,
            'playerStats' => $playerStats,
            'activeTournaments' => $activeTournaments,
        ])->layout('components.layouts.dashboard', [
            'title' => 'Gamer Terminal | PlayerSaloons',
            'dashboard_title' => 'DASHBOARD',
        ]);
    }
}
