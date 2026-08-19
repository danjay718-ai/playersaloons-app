<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Database\Query\JoinClause;
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

        // Calculate all lifetime match statistics in one grouped query. Joining
        // only the current user's roster rows avoids growing IN (...) lists as a
        // player's tournament history gets larger.
        $matchStats = GameMatch::query()
            ->leftJoin('tournament_registrations as player_a_reg', 'player_a_reg.id', '=', 'matches.player_a_registration_id')
            ->leftJoin('tournament_registration_members as player_a_member', function (JoinClause $join) use ($user): void {
                $join->on('player_a_member.registration_id', '=', 'matches.player_a_registration_id')
                    ->where('player_a_member.user_id', '=', $user->id);
            })
            ->leftJoin('tournament_registrations as player_b_reg', 'player_b_reg.id', '=', 'matches.player_b_registration_id')
            ->leftJoin('tournament_registration_members as player_b_member', function (JoinClause $join) use ($user): void {
                $join->on('player_b_member.registration_id', '=', 'matches.player_b_registration_id')
                    ->where('player_b_member.user_id', '=', $user->id);
            })
            ->leftJoin('tournament_registrations as winner_reg', 'winner_reg.id', '=', 'matches.winner_registration_id')
            ->leftJoin('tournament_registration_members as winner_member', function (JoinClause $join) use ($user): void {
                $join->on('winner_member.registration_id', '=', 'matches.winner_registration_id')
                    ->where('winner_member.user_id', '=', $user->id);
            })
            ->whereIn('matches.status', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value])
            ->where(function ($query) use ($user): void {
                $query->where('player_a_reg.user_id', $user->id)
                    ->orWhereNotNull('player_a_member.user_id')
                    ->orWhere('player_b_reg.user_id', $user->id)
                    ->orWhereNotNull('player_b_member.user_id');
            })
            ->selectRaw(
                'matches.tournament_id,
                 SUM(CASE WHEN winner_reg.user_id = ? OR winner_member.user_id IS NOT NULL THEN 1 ELSE 0 END) AS wins,
                 SUM(CASE WHEN matches.winner_registration_id IS NOT NULL AND winner_reg.user_id <> ? AND winner_member.user_id IS NULL THEN 1 ELSE 0 END) AS losses',
                [$user->id, $user->id],
            )
            ->groupBy('matches.tournament_id')
            ->get();

        $lostTournamentIds = $matchStats
            ->filter(fn ($stats): bool => (int) $stats->losses > 0)
            ->pluck('tournament_id')
            ->all();
        $matchWins = (int) $matchStats->sum('wins');
        $matchLosses = (int) $matchStats->sum('losses');

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

        // The exact active/history totals are already queried above, so pass
        // the selected total to the paginator instead of issuing another
        // COUNT(*). This retains the full paginator API used by the view.
        $tournaments = $query->orderBy('created_at', 'desc')->paginate(
            perPage: 10,
            total: $this->tSubTab === 'active' ? $activeCount : $historyCount,
        );

        // Eager-load all matches for the current paginated tournaments to prevent N+1 queries in loop
        $tournamentIds = $tournaments->pluck('id')->toArray();
        $userMatches = collect();
        if (! empty($tournamentIds)) {
            $userMatches = GameMatch::whereIn('tournament_id', $tournamentIds)
                ->where(function ($query) use ($user): void {
                    $query->whereHas('playerARegistration', function ($registration) use ($user): void {
                        $registration->where('user_id', $user->id)
                            ->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
                    })->orWhereHas('playerBRegistration', function ($registration) use ($user): void {
                        $registration->where('user_id', $user->id)
                            ->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
                    });
                })
                ->with(['tournament', 'round', 'playerARegistration.user', 'playerBRegistration.user', 'winnerRegistration'])
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('tournament_id');
        }

        $activeMatchRooms = $userMatches
            ->flatten(1)
            ->filter(fn (GameMatch $match): bool => in_array($match->status, [
                MatchStatus::READY,
                MatchStatus::IN_PROGRESS,
                MatchStatus::RESULT_SUBMITTED,
                MatchStatus::WAITING_FOR_CONFIRMATION,
                MatchStatus::DISPUTED,
            ], true))
            ->sortByDesc('updated_at')
            ->values();

        return view('livewire.tournament.my-tournaments-list', [
            'tournaments' => $tournaments,
            'userMatches' => $userMatches,
            'activeCount' => $activeCount,
            'historyCount' => $historyCount,
            'matchWins' => $matchWins,
            'matchLosses' => $matchLosses,
            'activeMatchRooms' => $activeMatchRooms,
        ])->layout('components.layouts.dashboard', ['title' => 'My Tournaments | PlayerSaloons', 'dashboard_title' => 'MY TOURNAMENTS']);
    }
}
