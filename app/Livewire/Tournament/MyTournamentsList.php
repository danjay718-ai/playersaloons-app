<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MyTournamentsList extends Component
{
    use WithPagination;

    public string $tSubTab = 'active'; // active or history

    protected $queryString = [
        'tSubTab' => ['except' => 'active'],
    ];

    public function render()
    {
        $user = Auth::user();

        // 1. Calculate Stats
        $activeCount = Tournament::query()
            ->whereHas('registrations', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            })
            ->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
            ->count();

        $historyCount = Tournament::query()
            ->whereHas('registrations', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            })
            ->whereIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
            ->count();

        $registrations = \App\Modules\Tournament\Models\TournamentRegistration::where('user_id', $user->id)
            ->whereNotIn('status', [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED])
            ->pluck('id');

        $matchWins = 0;
        $matchLosses = 0;

        if ($registrations->isNotEmpty()) {
            $matchWins = \App\Modules\Match\Models\GameMatch::whereIn('status', [\App\Shared\Enums\MatchStatus::COMPLETED, \App\Shared\Enums\MatchStatus::FORFEITED])
                ->whereIn('winner_registration_id', $registrations)
                ->count();

            $matchLosses = \App\Modules\Match\Models\GameMatch::whereIn('status', [\App\Shared\Enums\MatchStatus::COMPLETED, \App\Shared\Enums\MatchStatus::FORFEITED])
                ->where(function ($query) use ($registrations) {
                    $query->whereIn('player_a_registration_id', $registrations)
                          ->orWhereIn('player_b_registration_id', $registrations);
                })
                ->whereNotNull('winner_registration_id')
                ->whereNotIn('winner_registration_id', $registrations)
                ->count();
        }

        // 2. Fetch Tournaments
        $query = Tournament::query()
            ->whereHas('registrations', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            })
            ->with('game.translations');

        if ($this->tSubTab === 'active') {
            $query->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value]);
        } else {
            $query->whereIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
                  ->with([
                      'registrations' => function ($q) use ($user) {
                          $q->where('user_id', $user->id);
                      }
                  ]);
        }

        return view('livewire.tournament.my-tournaments-list', [
            'tournaments' => $query->orderBy('created_at', 'desc')->paginate(10),
            'activeCount' => $activeCount,
            'historyCount' => $historyCount,
            'matchWins' => $matchWins,
            'matchLosses' => $matchLosses,
        ])->layout('components.layouts.dashboard', ['title' => 'My Tournaments | PlayerSaloons', 'dashboard_title' => 'MY TOURNAMENTS']);
    }
}
