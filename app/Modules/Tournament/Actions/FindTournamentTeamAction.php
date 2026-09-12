<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Community\Services\ChatService;
use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserGameAccount;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTeam;
use App\Modules\Tournament\Models\TournamentTeamMember;
use App\Modules\Tournament\Models\TournamentTeamSearchEntry;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FindTournamentTeamAction
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ChatService $chat,
    ) {}

    public function execute(Tournament $tournament, User $user, string $gameIdValue, string $readyMode, ?int $platformId = null): ?TournamentTeam
    {
        if ($tournament->status !== TournamentStatus::REGISTRATION_OPEN || (int) $tournament->team_size <= 1 || $tournament->platform_id === null) {
            throw new \LogicException('Team finding is not available for this tournament.');
        }

        $platformId ??= $tournament->platform_id;
        if (! in_array($platformId, $tournament->supportedPlatformIds(), true)) {
            throw new \LogicException('Select a platform supported by this competition.');
        }

        return DB::transaction(function () use ($tournament, $user, $gameIdValue, $readyMode, $platformId): ?TournamentTeam {
            $alreadyInTeam = TournamentTeamMember::query()
                ->where('tournament_id', $tournament->getKey())
                ->where('user_id', $user->getKey())
                ->exists();
            if ($alreadyInTeam) {
                return TournamentTeam::query()
                    ->where('tournament_id', $tournament->getKey())
                    ->whereHas('members', fn ($members) => $members->where('user_id', $user->getKey()))
                    ->first();
            }

            TournamentTeamSearchEntry::query()->updateOrCreate([
                'tournament_id' => $tournament->getKey(),
                'user_id' => $user->getKey(),
            ], [
                'platform_id' => $platformId,
                'game_id_value' => trim($gameIdValue),
                'ready_mode' => $readyMode,
                'status' => 'searching',
                'matched_at' => null,
            ]);

            $entries = TournamentTeamSearchEntry::query()
                ->where('tournament_id', $tournament->getKey())
                ->where('platform_id', $platformId)
                ->where('status', 'searching')
                ->oldest('id')
                ->lockForUpdate()
                ->limit((int) $tournament->team_size)
                ->get();

            if ($entries->count() < (int) $tournament->team_size) {
                return null;
            }

            $leader = $entries->first();
            $team = TournamentTeam::query()->create([
                'uuid' => Str::uuid()->toString(),
                'tournament_id' => $tournament->getKey(),
                'leader_user_id' => $leader->user_id,
                'name' => 'Open Team '.strtoupper(Str::random(4)),
                'status' => 'ready',
            ]);

            TournamentTeamMember::query()->insert($entries->values()->map(fn ($entry, int $index): array => [
                'tournament_id' => $tournament->getKey(),
                'tournament_team_id' => $team->getKey(),
                'user_id' => $entry->user_id,
                'role' => $index === 0 ? 'leader' : 'member',
                'game_id_value' => $entry->game_id_value,
                'ready_mode' => $entry->ready_mode,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all());

            TournamentTeamSearchEntry::query()->whereIn('id', $entries->pluck('id'))->update([
                'status' => 'matched',
                'matched_at' => now(),
                'updated_at' => now(),
            ]);

            $players = User::query()->whereIn('id', $entries->pluck('user_id'))->get();
            foreach ($players as $player) {
                UserGameAccount::query()->updateOrCreate([
                    'user_id' => $player->id,
                    'game_id' => $tournament->game_id,
                    'platform_id' => $platformId,
                ], [
                    'game_id_value' => (string) $entries->firstWhere('user_id', $player->id)?->game_id_value,
                ]);
                $this->notifications->send(
                    $player,
                    'tournament_team_formed',
                    'Your Tournament Team Is Ready',
                    "Your team for '{$tournament->name}' is complete. The Team Leader can now finalize registration.",
                );
            }

            $team->load('members.user');
            $this->chat->tournamentTeamConversation($team, $players->firstWhere('id', $leader->user_id));

            return $team;
        });
    }
}
