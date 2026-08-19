<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\PlayerExperienceAward;
use App\Modules\Identity\Models\PlayerProgression;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class PlayerProgressionService
{
    public const TOURNAMENT_COMPLETION_XP = 100;

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
            Tournament::query()
                ->where('status', TournamentStatus::COMPLETED->value)
                ->where(function ($query) use ($user): void {
                    $query->whereHas('registrations', function ($registrations) use ($user): void {
                        $registrations->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
                            ->where(function ($eligible) use ($user): void {
                                $eligible->where('user_id', $user->getKey())
                                    ->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $user->getKey()));
                            });
                    });
                })
                ->whereNotExists(function ($awards) use ($user): void {
                    $awards->selectRaw('1')
                        ->from('player_experience_awards')
                        ->whereColumn('player_experience_awards.source_id', 'tournaments.id')
                        ->where('player_experience_awards.user_id', $user->getKey())
                        ->where('player_experience_awards.source_type', 'tournament')
                        ->where('player_experience_awards.reason', 'completion');
                })
                ->pluck('id')
                ->each(fn ($tournamentId) => $this->awardTournamentCompletion((int) $user->getKey(), (int) $tournamentId));

            return true;
        });
    }

    public function awardTournamentCompletion(int $userId, int $tournamentId): void
    {
        if (! $this->tablesReady()) {
            return;
        }

        DB::transaction(function () use ($userId, $tournamentId): void {
            $award = PlayerExperienceAward::query()->firstOrCreate([
                'user_id' => $userId,
                'source_type' => 'tournament',
                'source_id' => $tournamentId,
                'reason' => 'completion',
            ], [
                'uuid' => Str::uuid()->toString(),
                'amount' => self::TOURNAMENT_COMPLETION_XP,
                'metadata' => ['version' => 1],
            ]);

            if (! $award->wasRecentlyCreated) {
                return;
            }

            PlayerProgression::query()->firstOrCreate(
                ['user_id' => $userId],
                ['experience_points' => 0, 'level' => 1, 'tournaments_completed' => 0],
            );

            $progression = PlayerProgression::query()->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            $newXp = $progression->experience_points + self::TOURNAMENT_COMPLETION_XP;
            $progression->update([
                'experience_points' => $newXp,
                'level' => intdiv($newXp, PlayerProgression::XP_PER_LEVEL) + 1,
                'tournaments_completed' => $progression->tournaments_completed + 1,
            ]);
        });

        Cache::forget("player-dashboard:snapshot:v4:{$userId}");
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
