<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\HeadToHeadMatchStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $historyCount = $this->matchHistoryCount((int) $user->id);
        $historyMatches = $this->tSubTab === 'history'
            ? $this->matchHistory((int) $user->id)
            : new LengthAwarePaginator([], $historyCount, 10, 1, ['pageName' => 'historyPage']);

        $matchWins += HeadToHeadMatch::query()
            ->where('winner_user_id', $user->id)
            ->where('status', HeadToHeadMatchStatus::COMPLETED)
            ->count();
        $matchLosses += HeadToHeadMatch::query()
            ->where(fn ($query) => $query->where('creator_user_id', $user->id)->orWhere('opponent_user_id', $user->id))
            ->whereNotNull('winner_user_id')
            ->where('winner_user_id', '!=', $user->id)
            ->where('status', HeadToHeadMatchStatus::COMPLETED)
            ->count();

        $tournaments = new LengthAwarePaginator([], $activeCount, 10, 1, ['pageName' => 'tournamentPage']);
        $userMatches = collect();
        if ($this->tSubTab === 'active') {
            $tournaments = Tournament::query()
                ->whereHas('registrations', function ($q) use ($user) {
                    $q->where(function ($registration) use ($user) {
                        $registration->where('user_id', $user->id)->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->id));
                    })->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value]);
                })
                ->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value])
                ->whereNotIn('id', $lostTournamentIds)
                ->with('game.translations')
                ->withCount(['registrations' => fn ($query) => $query->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])])
                ->orderByDesc('created_at')
                ->paginate(10, ['*'], 'tournamentPage', total: $activeCount);

            $tournamentIds = $tournaments->pluck('id')->all();
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
            'historyMatches' => $historyMatches,
            'matchWins' => $matchWins,
            'matchLosses' => $matchLosses,
            'activeMatchRooms' => $activeMatchRooms,
        ])->layout('components.layouts.dashboard', ['title' => 'My Tournaments | PlayerSaloons', 'dashboard_title' => 'MY TOURNAMENTS']);
    }

    /** Count completed matches without hydrating tournament graphs. */
    private function matchHistoryCount(int $userId): int
    {
        $tournamentCount = GameMatch::query()
            ->whereIn('status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
            ->where(function ($query) use ($userId): void {
                $query->whereHas('playerARegistration', fn ($registration) => $registration
                    ->where('user_id', $userId)
                    ->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $userId)))
                    ->orWhereHas('playerBRegistration', fn ($registration) => $registration
                        ->where('user_id', $userId)
                        ->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $userId)));
            })
            ->count();

        $headToHeadCount = HeadToHeadMatch::query()
            ->where(fn ($query) => $query->where('creator_user_id', $userId)->orWhere('opponent_user_id', $userId))
            ->whereIn('status', [
                HeadToHeadMatchStatus::COMPLETED,
                HeadToHeadMatchStatus::CANCELLED,
                HeadToHeadMatchStatus::EXPIRED,
            ])
            ->count();

        return $tournamentCount + $headToHeadCount;
    }

    /**
     * Build one lightweight, chronologically ordered history across tournament
     * and head-to-head matches, then hydrate only the records on this page.
     */
    private function matchHistory(int $userId): LengthAwarePaginator
    {
        $tournamentMatches = DB::table('matches as match_history_source')
            ->leftJoin('tournament_registrations as history_a', 'history_a.id', '=', 'match_history_source.player_a_registration_id')
            ->leftJoin('tournament_registration_members as history_a_member', function (JoinClause $join) use ($userId): void {
                $join->on('history_a_member.registration_id', '=', 'match_history_source.player_a_registration_id')
                    ->where('history_a_member.user_id', '=', $userId);
            })
            ->leftJoin('tournament_registrations as history_b', 'history_b.id', '=', 'match_history_source.player_b_registration_id')
            ->leftJoin('tournament_registration_members as history_b_member', function (JoinClause $join) use ($userId): void {
                $join->on('history_b_member.registration_id', '=', 'match_history_source.player_b_registration_id')
                    ->where('history_b_member.user_id', '=', $userId);
            })
            ->whereIn('match_history_source.status', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value])
            ->where(function ($query) use ($userId): void {
                $query->where('history_a.user_id', $userId)
                    ->orWhereNotNull('history_a_member.user_id')
                    ->orWhere('history_b.user_id', $userId)
                    ->orWhereNotNull('history_b_member.user_id');
            })
            ->selectRaw("match_history_source.id as source_id, 'tournament' as match_type, COALESCE(match_history_source.completed_at, match_history_source.updated_at) as sort_at");

        $headToHeadMatches = DB::table('head_to_head_matches as h2h_history_source')
            ->where(fn ($query) => $query->where('creator_user_id', $userId)->orWhere('opponent_user_id', $userId))
            ->whereIn('status', [
                HeadToHeadMatchStatus::COMPLETED->value,
                HeadToHeadMatchStatus::CANCELLED->value,
                HeadToHeadMatchStatus::EXPIRED->value,
            ])
            ->selectRaw("h2h_history_source.id as source_id, 'head_to_head' as match_type, COALESCE(h2h_history_source.completed_at, h2h_history_source.cancelled_at, h2h_history_source.updated_at) as sort_at");

        $history = DB::query()
            ->fromSub($tournamentMatches->unionAll($headToHeadMatches), 'combined_match_history')
            ->orderByDesc('sort_at')
            ->paginate(10, ['*'], 'historyPage');

        $tournamentIds = $history->getCollection()->where('match_type', 'tournament')->pluck('source_id');
        $headToHeadIds = $history->getCollection()->where('match_type', 'head_to_head')->pluck('source_id');

        $tournamentModels = GameMatch::query()
            ->with([
                'tournament.game.translations', 'round:id,round_number',
                'playerARegistration.user:id,username', 'playerARegistration.team:id,name', 'playerARegistration.rosterMembers:id,registration_id,user_id',
                'playerBRegistration.user:id,username', 'playerBRegistration.team:id,name', 'playerBRegistration.rosterMembers:id,registration_id,user_id',
            ])
            ->whereIn('id', $tournamentIds)
            ->get()
            ->keyBy('id');
        $headToHeadModels = HeadToHeadMatch::query()
            ->with(['creator:id,username', 'opponent:id,username', 'winner:id,username', 'game.translations', 'platform:id,name'])
            ->whereIn('id', $headToHeadIds)
            ->get()
            ->keyBy('id');

        $history->setCollection($history->getCollection()->map(function ($row) use ($userId, $tournamentModels, $headToHeadModels): ?array {
            if ($row->match_type === 'head_to_head') {
                $match = $headToHeadModels->get($row->source_id);
                if (! $match) {
                    return null;
                }
                $opponent = (int) $match->creator_user_id === $userId ? $match->opponent : $match->creator;

                return [
                    'type' => 'head_to_head', 'label' => 'Head to Head', 'uuid' => $match->uuid,
                    'game' => $match->game->localizedName(), 'opponent' => $opponent?->username ?? 'Opponent',
                    'round' => null, 'status' => $match->status->value,
                    'result' => $match->winner_user_id ? ((int) $match->winner_user_id === $userId ? 'won' : 'lost') : 'closed',
                    'date' => $match->completed_at ?? $match->cancelled_at ?? $match->updated_at,
                    'href' => '/head-to-head?activeTab=history#duel-'.$match->uuid,
                    'tournament' => null,
                ];
            }

            $match = $tournamentModels->get($row->source_id);
            if (! $match) {
                return null;
            }
            $userSide = $match->playerARegistration?->includesUser($userId) ? $match->playerARegistration : $match->playerBRegistration;
            $opponentSide = $userSide?->id === $match->player_a_registration_id ? $match->playerBRegistration : $match->playerARegistration;

            return [
                'type' => 'tournament', 'label' => 'Tournament', 'uuid' => $match->uuid,
                'game' => $match->tournament->game->localizedName(),
                'opponent' => $opponentSide?->team?->name ?? $opponentSide?->user?->username ?? 'Opponent',
                'round' => $match->round?->round_number, 'status' => $match->status->value,
                'result' => $match->winner_registration_id ? ((int) $match->winner_registration_id === (int) $userSide?->id ? 'won' : 'lost') : 'closed',
                'date' => $match->completed_at ?? $match->updated_at,
                'href' => '/matches/'.$match->uuid,
                'tournament' => $match->tournament->name,
            ];
        })->filter()->values());

        return $history;
    }
}
