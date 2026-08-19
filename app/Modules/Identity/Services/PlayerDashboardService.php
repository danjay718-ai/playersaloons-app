<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Community\Models\BroadcastMessage;
use App\Modules\Community\Models\ChatConversation;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\PlayerFollow;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class PlayerDashboardService
{
    public function __construct(
        private readonly PlayerProgressionService $progression,
        private readonly UserPresenceService $presence,
    ) {}

    /** @return array<string, mixed> */
    public function dataFor(User $user): array
    {
        $progression = $this->progression->progressionFor($user);

        return array_merge(
            Cache::remember("player-dashboard:snapshot:v4:{$user->getKey()}", now()->addSeconds(45), fn () => $this->snapshot($user)),
            [
                'progression' => $progression,
                'announcements' => $this->announcements(),
                'globalMessages' => $this->globalMessages(),
                'onlineFollowing' => $this->onlineFollowing($user),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        $eligibleRegistration = fn (Builder $query) => $query
            ->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])
            ->where(function (Builder $players) use ($user): void {
                $players->where('user_id', $user->getKey())
                    ->orWhereHas('rosterMembers', fn (Builder $members) => $members->where('user_id', $user->getKey()));
            });

        $activeTournamentQuery = Tournament::query()
            ->whereHas('registrations', $eligibleRegistration)
            ->whereNotIn('status', [TournamentStatus::COMPLETED->value, TournamentStatus::CANCELLED->value, TournamentStatus::REFUNDED->value]);

        $activeTournamentCount = (clone $activeTournamentQuery)->count();
        $activeTournaments = $activeTournamentQuery
            ->with(['game.translations', 'platform'])
            ->withCount(['registrations' => fn (Builder $query) => $query->whereNotIn('status', [RegistrationStatus::CANCELLED->value, RegistrationStatus::REFUNDED->value])])
            ->orderBy('start_at')
            ->limit(4)
            ->get()
            ->map(fn (Tournament $tournament): array => [
                'uuid' => $tournament->uuid,
                'name' => $tournament->name,
                'game' => $tournament->game?->localizedName() ?? 'Game',
                'status' => $tournament->status->value,
                'starts_at' => $tournament->start_at?->format('M d · H:i') ?? 'TBD',
                'registrations_count' => (int) $tournament->registrations_count,
                'max_participants' => (int) $tournament->max_participants,
            ])
            ->all();

        $playerMatch = function (Builder $query) use ($user): void {
            $query->where('user_id', $user->getKey())
                ->orWhereHas('rosterMembers', fn (Builder $members) => $members->where('user_id', $user->getKey()));
        };

        $matchesQuery = GameMatch::query()->where(function (Builder $query) use ($playerMatch): void {
            $query->whereHas('playerARegistration', $playerMatch)
                ->orWhereHas('playerBRegistration', $playerMatch);
        });

        $completedMatches = (clone $matchesQuery)->whereIn('status', [MatchStatus::COMPLETED->value, MatchStatus::FORFEITED->value]);
        $wins = (clone $completedMatches)->whereHas('winnerRegistration', $playerMatch)->count();
        $matchesPlayed = (clone $completedMatches)->count();

        $recentMatches = $matchesQuery
            ->with(['tournament.game.translations', 'playerARegistration.rosterMembers', 'playerBRegistration.rosterMembers'])
            ->latest('updated_at')
            ->limit(4)
            ->get()
            ->map(function (GameMatch $match) use ($user): array {
                $isPlayerA = $this->registrationContains($match->playerARegistration, (int) $user->getKey());
                $playerRegistrationId = $isPlayerA ? $match->player_a_registration_id : $match->player_b_registration_id;
                $status = $match->status->value;
                $outcome = in_array($match->status, [MatchStatus::COMPLETED, MatchStatus::FORFEITED], true)
                    ? ((int) $match->winner_registration_id === (int) $playerRegistrationId ? 'win' : 'loss')
                    : 'pending';

                return [
                    'uuid' => $match->uuid,
                    'tournament_uuid' => $match->tournament->uuid,
                    'tournament' => $match->tournament->name,
                    'game' => $match->tournament->game?->localizedName() ?? 'Game',
                    'status' => str_replace('_', ' ', $status),
                    'outcome' => $outcome,
                    'updated_at' => $match->updated_at?->diffForHumans(),
                ];
            })
            ->all();

        $wallet = $user->wallet()
            ->withSum(['ledgerEntries as prize_earnings' => fn (Builder $query) => $query->where('type', LedgerType::PRIZE->value)], 'amount')
            ->first();

        return [
            'activeTournaments' => $activeTournaments,
            'recentMatches' => $recentMatches,
            'stats' => [
                'active_tournaments' => $activeTournamentCount,
                'matches_played' => $matchesPlayed,
                'wins' => $wins,
                'losses' => max(0, $matchesPlayed - $wins),
                'earnings' => (float) ($wallet?->prize_earnings ?? 0),
                'balance' => (float) ($wallet?->cached_balance ?? 0),
            ],
        ];
    }

    private function announcements()
    {
        return Cache::remember('player-dashboard:announcements:v3', now()->addMinute(), fn () => BroadcastMessage::query()
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn (BroadcastMessage $announcement): array => [
                'title' => $announcement->title,
                'message' => $announcement->message,
                'created_at' => $announcement->created_at?->diffForHumans(),
            ])
            ->all());
    }

    private function globalMessages()
    {
        return Cache::remember('player-dashboard:global-chat:v3', now()->addSeconds(15), function (): array {
            $conversationId = ChatConversation::query()->where('scope_key', 'global')->value('id');

            if ($conversationId === null) {
                return [];
            }

            return ChatMessage::query()
                ->with('user.profile')
                ->where('chat_conversation_id', $conversationId)
                ->latest()
                ->limit(5)
                ->get()
                ->reverse()
                ->values()
                ->map(function (ChatMessage $message): array {
                    $username = $message->user?->username ?? 'System';

                    return [
                        'display_name' => $message->user?->profile?->display_name ?: $username,
                        'username' => $username,
                        'avatar_url' => $message->user?->profile?->avatar_url,
                        'body' => $message->body,
                    ];
                })
                ->all();
        });
    }

    private function onlineFollowing(User $user)
    {
        return Cache::remember("player-dashboard:online-following:v3:{$user->getKey()}", now()->addSeconds(20), function () use ($user): array {
            $onlineIds = $this->presence->onlineUserIds();

            if ($onlineIds === []) {
                return [];
            }

            return PlayerFollow::query()
                ->with('followed.profile')
                ->where('follower_user_id', $user->getKey())
                ->whereIn('followed_user_id', $onlineIds)
                ->latest()
                ->limit(6)
                ->get()
                ->pluck('followed')
                ->filter()
                ->values()
                ->map(function (User $onlinePlayer): array {
                    return [
                        'username' => $onlinePlayer->username,
                        'display_name' => $onlinePlayer->profile?->display_name ?: $onlinePlayer->username,
                        'avatar_url' => $onlinePlayer->profile?->avatar_url,
                    ];
                })
                ->all();
        });
    }

    private function registrationContains(mixed $registration, int $userId): bool
    {
        return $registration !== null && (
            (int) $registration->user_id === $userId
            || $registration->rosterMembers->contains('user_id', $userId)
        );
    }
}
