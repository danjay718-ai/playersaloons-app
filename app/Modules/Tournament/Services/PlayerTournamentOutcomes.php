<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PlayerTournamentOutcomes
{
    /** @return Collection<int, 'active'|'closed'|'defeat'|'victory'> Tournament ID => exclusive player outcome. */
    public function forPlayer(int $userId): Collection
    {
        $registrations = TournamentRegistration::query()
            ->where(fn ($query) => $query->where('user_id', $userId)
                ->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $userId)));
        $registrationIds = (clone $registrations)->select('tournament_registrations.id');

        $loss = GameMatch::query()
            ->selectRaw('1')->limit(1)
            ->whereColumn('matches.tournament_id', 'tournaments.id')
            ->whereIn('matches.status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
            ->whereNotNull('matches.winner_registration_id')
            ->whereNotIn('matches.winner_registration_id', clone $registrationIds)
            ->where(fn ($query) => $query->whereIn('matches.player_a_registration_id', clone $registrationIds)
                ->orWhereIn('matches.player_b_registration_id', clone $registrationIds));

        // Inspect the actual final round, including an unresolved final. An
        // earlier round's winner must never become the tournament champion.
        $finalWinner = GameMatch::query()
            ->join('rounds', 'rounds.id', '=', 'matches.round_id')
            ->whereColumn('matches.tournament_id', 'tournaments.id')
            ->where('rounds.round_number', DB::table('rounds as final_rounds')
                ->join('brackets', 'brackets.id', '=', 'final_rounds.bracket_id')
                ->whereColumn('brackets.tournament_id', 'tournaments.id')
                ->selectRaw('MAX(final_rounds.round_number)'))
            ->orderByDesc('rounds.round_number')->orderBy('matches.id')
            ->selectRaw('CASE WHEN matches.status IN (?, ?) THEN matches.winner_registration_id ELSE NULL END', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value])
            ->limit(1);

        return Tournament::query()
            ->where('competition_type', CompetitionType::TOURNAMENT)
            ->whereIn('id', (clone $registrations)->select('tournament_id'))
            ->select(['id', 'status', 'completion_reason'])
            ->selectSub($loss, 'player_eliminated')
            ->selectSub($finalWinner, 'champion_registration_id')
            ->with(['registrations' => fn ($query) => $query
                ->whereIn('id', clone $registrationIds)
                ->select(['id', 'tournament_id', 'status'])])
            ->get()
            ->mapWithKeys(function (Tournament $tournament): array {
                $outcome = 'active';
                $completed = $tournament->status === TournamentStatus::COMPLETED;
                $champion = $tournament->getAttribute('champion_registration_id');
                if (in_array($tournament->status, [TournamentStatus::CANCELLED, TournamentStatus::REFUNDED], true)) {
                    $outcome = 'closed';
                } elseif ($completed && ($tournament->completion_reason === 'no_champion' || $champion === null)) {
                    $outcome = 'closed';
                } elseif ($completed && $tournament->registrations->contains('id', (int) $champion)) {
                    $outcome = 'victory';
                } elseif ($tournament->getAttribute('player_eliminated')) {
                    $outcome = 'defeat';
                } elseif (! $tournament->registrations->contains(fn (TournamentRegistration $registration): bool => ! in_array($registration->status, [RegistrationStatus::CANCELLED, RegistrationStatus::REFUNDED], true))) {
                    $outcome = 'closed';
                } elseif ($completed) {
                    $outcome = 'defeat';
                }

                return [(int) $tournament->id => $outcome];
            });
    }
}
