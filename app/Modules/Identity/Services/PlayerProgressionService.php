<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\PlayerExperienceAward;
use App\Modules\Identity\Models\PlayerProgression;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PlayerProgressionService
{
    public const TOURNAMENT_COMPLETION_XP = 100;

    public const V2_PARTICIPATION_XP = 10;

    public function __construct(private readonly NotificationService $notifications) {}

    public function progressionFor(User $user): PlayerProgression
    {
        if (! $this->tablesReady()) {
            return new PlayerProgression([
                'user_id' => $user->getKey(),
                'experience_points' => 0,
                'level' => 1,
                'tournaments_completed' => 0,
            ]);
        }

        $this->reconcileCompletedTournaments($user);

        return PlayerProgression::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['experience_points' => 0, 'level' => 1, 'tournaments_completed' => 0],
        );
    }

    public function reconcileCompletedTournaments(User $user): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        Cache::remember("player-progression:reconciled:v1:{$user->getKey()}", now()->addMinutes(10), function () use ($user): bool {
            TournamentRegistration::query()
                ->select('tournament_registrations.tournament_id')
                ->whereNotIn('tournament_registrations.status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
                ->where(function ($eligible) use ($user): void {
                    $eligible->where('tournament_registrations.user_id', $user->getKey())
                        ->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->getKey()));
                })
                ->whereHas('tournament', fn ($tournaments) => $tournaments
                    ->where('workflow_version', 1)
                    ->where('status', TournamentStatus::COMPLETED->value))
                ->whereExists(function ($matches): void {
                    $matches->selectRaw('1')->from('matches')
                        ->where('matches.status', MatchStatus::COMPLETED->value)
                        ->whereNotNull('matches.started_at')
                        ->where(function ($players): void {
                            $players->whereColumn('matches.player_a_registration_id', 'tournament_registrations.id')
                                ->orWhereColumn('matches.player_b_registration_id', 'tournament_registrations.id');
                        });
                })
                ->whereNotExists(function ($awards) use ($user): void {
                    $awards->selectRaw('1')
                        ->from('player_experience_awards')
                        ->whereColumn('player_experience_awards.source_id', 'tournament_registrations.tournament_id')
                        ->where('player_experience_awards.user_id', $user->getKey())
                        ->where('player_experience_awards.source_type', 'tournament')
                        ->where('player_experience_awards.reason', 'completion');
                })
                ->distinct()
                ->pluck('tournament_id')
                ->each(fn ($tournamentId) => $this->awardTournamentCompletion((int) $user->getKey(), (int) $tournamentId));

            return true;
        });
    }

    public function awardTournamentCompletion(int $userId, int $tournamentId): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        $tournament = Tournament::query()->find($tournamentId);
        if ($tournament === null) {
            return;
        }

        $this->award(
            $userId,
            $tournamentId,
            'completion',
            (int) ($tournament->play_xp ?: self::TOURNAMENT_COMPLETION_XP),
            true,
        );
    }

    public function awardTournamentChampion(int $userId, int $tournamentId, int $amount): void
    {
        if ($amount <= 0 || ! $this->tablesReady()) {
            return;
        }

        $this->award($userId, $tournamentId, 'champion_bonus', $amount, false);
    }

    public function awardV2TournamentParticipation(int $userId, int $tournamentId): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        $this->award($userId, $tournamentId, 'participation', self::V2_PARTICIPATION_XP, true);
    }

    private function award(int $userId, int $tournamentId, string $reason, int $amount, bool $incrementCompleted): void
    {
        $wasCreated = false;
        DB::transaction(function () use ($userId, $tournamentId, $reason, $amount, $incrementCompleted, &$wasCreated): void {
            $award = PlayerExperienceAward::query()->firstOrCreate([
                'user_id' => $userId,
                'source_type' => 'tournament',
                'source_id' => $tournamentId,
                'reason' => $reason,
            ], [
                'uuid' => Str::uuid()->toString(),
                'amount' => $amount,
                'metadata' => ['version' => 2],
            ]);

            if (! $award->wasRecentlyCreated) {
                return;
            }
            $wasCreated = true;

            PlayerProgression::query()->firstOrCreate(
                ['user_id' => $userId],
                ['experience_points' => 0, 'level' => 1, 'tournaments_completed' => 0],
            );

            $progression = PlayerProgression::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $newXp = $progression->experience_points + $amount;
            $progression->update([
                'experience_points' => $newXp,
                'level' => intdiv($newXp, PlayerProgression::XP_PER_LEVEL) + 1,
                'tournaments_completed' => $progression->tournaments_completed + ($incrementCompleted ? 1 : 0),
            ]);
        });

        Cache::forget("player-dashboard:snapshot:v4:{$userId}");

        if ($wasCreated && ($user = User::query()->find($userId)) !== null) {
            $title = $reason === 'champion_bonus' ? 'Winner Bonus XP Earned' : 'Play XP Earned';
            $this->notifications->send($user, 'tournament_xp_awarded', $title, "You earned {$amount} XP from your tournament performance.");
        }
    }

    private function tablesReady(): bool
    {
        return Cache::remember(
            'player-progression:schema-ready:v1',
            now()->addMinute(),
            fn (): bool => Schema::hasTable('player_progressions')
                && Schema::hasTable('player_experience_awards'),
        );
    }
}
