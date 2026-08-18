<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\MatchStatus;
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
        $registrations = TournamentRegistration::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
        })
            ->whereNotIn('status', [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED])
            ->pluck('id');

        $lostTournamentIds = [];
        $matchWins = 0;
        $matchLosses = 0;

        if ($registrations->isNotEmpty()) {
            // Get tournament IDs where the user has lost a match
            $lostTournamentIds = GameMatch::whereIn('status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
                ->where(function ($query) use ($registrations) {
                    $query->whereIn('player_a_registration_id', $registrations)
                        ->orWhereIn('player_b_registration_id', $registrations);
                })
                ->whereNotNull('winner_registration_id')
                ->whereNotIn('winner_registration_id', $registrations)
                ->pluck('tournament_id')
                ->unique()
                ->toArray();

            $matchWins = GameMatch::whereIn('status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
                ->whereIn('winner_registration_id', $registrations)
                ->count();

            $matchLosses = GameMatch::whereIn('status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
                ->where(function ($query) use ($registrations) {
                    $query->whereIn('player_a_registration_id', $registrations)
                        ->orWhereIn('player_b_registration_id', $registrations);
                })
                ->whereNotNull('winner_registration_id')
                ->whereNotIn('winner_registration_id', $registrations)
                ->count();
        }

        $activeCount = Tournament::query()
            ->whereHas('registrations', function ($q) use ($user) {
                $q->where(function ($registration) use ($user) {
                    $registration->where('user_id', $user->id)->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
                })
                    ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            })
            ->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
            ->whereNotIn('id', $lostTournamentIds)
            ->count();

        $historyCount = Tournament::query()
            ->whereHas('registrations', function ($q) use ($user) {
                $q->where(function ($registration) use ($user) {
                    $registration->where('user_id', $user->id)->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
                })
                    ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            })
            ->where(function ($q) use ($lostTournamentIds) {
                $q->whereIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
                    ->orWhereIn('id', $lostTournamentIds);
            })
            ->count();

        // 2. Fetch Tournaments
        $query = Tournament::query()
            ->whereHas('registrations', function ($q) use ($user) {
                $q->where(function ($registration) use ($user) {
                    $registration->where('user_id', $user->id)->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
                })
                    ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            })
            ->with('game.translations')
            ->withCount(['registrations' => function ($q) {
                $q->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
            }]);

        if ($this->tSubTab === 'active') {
            $query->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
                ->whereNotIn('id', $lostTournamentIds);
        } else {
            $query->where(function ($q) use ($lostTournamentIds) {
                $q->whereIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
                    ->orWhereIn('id', $lostTournamentIds);
            })->with([
                'registrations' => function ($q) use ($user) {
                    $q->where(function ($registration) use ($user) {
                        $registration->where('user_id', $user->id)->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
                    });
                },
            ]);
        }

        $tournaments = $query->orderBy('created_at', 'desc')->paginate(10);

        // Eager-load all matches for the current paginated tournaments to prevent N+1 queries in loop
        $tournamentIds = $tournaments->pluck('id')->toArray();
        $userMatches = collect();
        if (! empty($tournamentIds) && $registrations->isNotEmpty()) {
            $userMatches = GameMatch::whereIn('tournament_id', $tournamentIds)
                ->where(function ($q) use ($registrations) {
                    $q->whereIn('player_a_registration_id', $registrations)
                        ->orWhereIn('player_b_registration_id', $registrations);
                })
                ->with(['round', 'playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration'])
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('tournament_id');
        }

        return view('livewire.tournament.my-tournaments-list', [
            'tournaments' => $tournaments,
            'userMatches' => $userMatches,
            'activeCount' => $activeCount,
            'historyCount' => $historyCount,
            'matchWins' => $matchWins,
            'matchLosses' => $matchLosses,
        ])->layout('components.layouts.dashboard', ['title' => 'My Tournaments | PlayerSaloons', 'dashboard_title' => 'MY TOURNAMENTS']);
    }
}
