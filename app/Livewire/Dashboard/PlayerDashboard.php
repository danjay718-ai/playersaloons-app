<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PlayerDashboard extends Component
{
    public function render()
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->to('/login');
        }

        // 1. Get user registration IDs
        $userRegistrationIds = TournamentRegistration::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
            ->pluck('id');

        // 2. Fetch matches (Ongoing/Ready/Submitted)
        $activeMatches = GameMatch::query()
            ->where(function ($q) use ($userRegistrationIds) {
                $q->whereIn('player_a_registration_id', $userRegistrationIds)
                  ->orWhereIn('player_b_registration_id', $userRegistrationIds);
            })
            ->with(['tournament', 'round', 'playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration.user'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // 3. Fetch active tournaments
        $activeTournaments = Tournament::query()
            ->whereHas('registrations', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            })
            ->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
            ->with('game.translations')
            ->get();

        return view('livewire.dashboard.player-dashboard', [
            'user' => $user,
            'activeMatches' => $activeMatches,
            'activeTournaments' => $activeTournaments,
        ])->layout('components.layouts.app', ['title' => 'Dashboard | PlayerSaloons']);
    }
}
