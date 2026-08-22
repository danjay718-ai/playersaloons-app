<?php

declare(strict_types=1);

namespace App\Modules\Community\Actions;

use App\Modules\Community\Events\ChatMessageSent;
use App\Modules\Community\Models\ChatConversation;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\ChatParticipant;
use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use App\Modules\Tournament\Models\TournamentTeam;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChatService
{
    public function globalConversation(User $user): ChatConversation
    {
        /** @var ChatConversation $conversation */
        $conversation = ChatConversation::query()->firstOrCreate(
            ['scope_key' => 'global'],
            [
                'uuid' => Str::uuid()->toString(),
                'type' => ChatConversation::TYPE_GLOBAL,
                'name' => 'Global Comms',
                'created_by_user_id' => $user->getKey(),
            ]
        );

        $this->ensureParticipant($conversation, $user, 'member');

        return $conversation;
    }

    public function directConversation(User $actor, User $recipient): ChatConversation
    {
        if ((int) $actor->getKey() === (int) $recipient->getKey()) {
            throw ValidationException::withMessages([
                'username' => 'Choose another player to start a direct chat.',
            ]);
        }

        $ids = [(int) $actor->getKey(), (int) $recipient->getKey()];
        sort($ids);
        $scopeKey = 'direct:'.$ids[0].':'.$ids[1];

        return DB::transaction(function () use ($actor, $recipient, $scopeKey): ChatConversation {
            /** @var ChatConversation $conversation */
            $conversation = ChatConversation::query()->firstOrCreate(
                ['scope_key' => $scopeKey],
                [
                    'uuid' => Str::uuid()->toString(),
                    'type' => ChatConversation::TYPE_DIRECT,
                    'name' => null,
                    'created_by_user_id' => $actor->getKey(),
                ]
            );

            $this->ensureParticipant($conversation, $actor, 'member');
            $this->ensureParticipant($conversation, $recipient, 'member');

            return $conversation->fresh(['participants.user']) ?? $conversation;
        });
    }

    public function teamConversation(Team $team, User $actor): ChatConversation
    {
        $this->authorizeTeamMember($team, $actor);

        return DB::transaction(function () use ($team, $actor): ChatConversation {
            /** @var ChatConversation $conversation */
            $conversation = ChatConversation::query()->firstOrCreate(
                ['scope_key' => 'team:'.$team->getKey()],
                [
                    'uuid' => Str::uuid()->toString(),
                    'type' => ChatConversation::TYPE_TEAM,
                    'name' => $team->name,
                    'team_id' => $team->getKey(),
                    'created_by_user_id' => $actor->getKey(),
                ]
            );

            $team->members()
                ->with('user')
                ->where('status', 'active')
                ->get()
                ->each(function ($member) use ($conversation): void {
                    $this->ensureParticipant($conversation, $member->user, $member->role);
                });

            return $conversation->fresh(['participants.user', 'team']) ?? $conversation;
        });
    }

    public function joinTeamConversation(Team $team, User $actor): ChatConversation
    {
        // Opening chat must never mutate squad membership. Joining a squad is
        // an explicit request/approval workflow managed from the Squads page.
        return $this->teamConversation($team, $actor);
    }

    public function tournamentTeamConversation(TournamentTeam $team, User $actor): ChatConversation
    {
        $team->loadMissing('members.user');
        if (! $team->members->contains('user_id', $actor->getKey())) {
            abort(403, 'Only tournament team members can open this chat.');
        }

        return DB::transaction(function () use ($team, $actor): ChatConversation {
            $conversation = ChatConversation::query()->firstOrCreate(
                ['scope_key' => 'tournament-team:'.$team->getKey()],
                [
                    'uuid' => Str::uuid()->toString(),
                    'type' => ChatConversation::TYPE_TOURNAMENT_TEAM,
                    'name' => $team->name,
                    'tournament_team_id' => $team->getKey(),
                    'created_by_user_id' => $actor->getKey(),
                ],
            );

            foreach ($team->members as $member) {
                if ($member->user !== null) {
                    $this->ensureParticipant($conversation, $member->user, $member->role);
                }
            }

            return $conversation->fresh(['participants.user', 'tournamentTeam']) ?? $conversation;
        });
    }

    public function sendMessage(ChatConversation $conversation, User $user, string $body): ChatMessage
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);

        if ($body === '') {
            throw ValidationException::withMessages(['message' => 'Enter a message first.']);
        }

        if (mb_strlen($body) > 1000) {
            throw ValidationException::withMessages(['message' => 'Messages must be 1000 characters or fewer.']);
        }

        if (! $this->canAccessConversation($conversation, $user)) {
            abort(403, 'You cannot send messages to this channel.');
        }

        $message = DB::transaction(function () use ($conversation, $user, $body): ChatMessage {
            $this->ensureParticipant($conversation, $user, 'member');

            /** @var ChatParticipant|null $participant */
            $participant = $conversation->participants()
                ->where('user_id', $user->getKey())
                ->first();

            if ($participant?->muted_until && $participant->muted_until->isFuture()) {
                throw ValidationException::withMessages(['message' => 'You are muted in this channel.']);
            }

            /** @var ChatMessage $message */
            $message = ChatMessage::query()->create([
                'uuid' => Str::uuid()->toString(),
                'chat_conversation_id' => $conversation->getKey(),
                'user_id' => $user->getKey(),
                'body' => $body,
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();
            $participant?->forceFill(['last_read_at' => now()])->save();
            $this->pruneGlobalMessages($conversation);

            $message->load('user');

            return $message;
        });

        try {
            $pendingBroadcast = broadcast(new ChatMessageSent($conversation->uuid, $this->messagePayload($message)));
            unset($pendingBroadcast);
        } catch (Throwable $exception) {
            Log::warning('Chat message broadcast failed.', [
                'conversation_uuid' => $conversation->uuid,
                'message_uuid' => $message->uuid,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $message;
    }

    public function canAccessConversation(ChatConversation $conversation, User $user): bool
    {
        if ($conversation->type === ChatConversation::TYPE_GLOBAL) {
            return $user->hasVerifiedEmail();
        }

        if ($conversation->type === ChatConversation::TYPE_TEAM && $conversation->team_id !== null) {
            return $conversation->team?->members()
                ->where('user_id', $user->getKey())
                ->where('status', 'active')
                ->exists() ?? false;
        }

        if ($conversation->type === ChatConversation::TYPE_TOURNAMENT_TEAM && $conversation->tournament_team_id !== null) {
            return $conversation->tournamentTeam?->members()->where('user_id', $user->getKey())->exists() ?? false;
        }

        return $conversation->participants()
            ->where('user_id', $user->getKey())
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function conversationPayload(ChatConversation $conversation, User $viewer): array
    {
        $conversation->loadMissing(['participants.user', 'team', 'tournamentTeam.tournament']);
        /** @var ChatParticipant|null $participant */
        $participant = $conversation->participants
            ->first(fn (ChatParticipant $candidate): bool => (int) $candidate->user_id === (int) $viewer->getKey());

        $title = $conversation->name ?? 'Direct Chat';
        $subtitle = 'Open channel';

        if ($conversation->type === ChatConversation::TYPE_DIRECT) {
            $other = $conversation->participants
                ->first(fn (ChatParticipant $participant): bool => (int) $participant->user_id !== (int) $viewer->getKey())
                ?->user;
            $title = $other?->username ?? 'Direct Chat';
            $subtitle = 'Player to player';
        } elseif ($conversation->type === ChatConversation::TYPE_TEAM) {
            $title = $conversation->team?->name ?? $conversation->name ?? 'Squad Chat';
            $subtitle = 'Permanent Squad Chat';
        } elseif ($conversation->type === ChatConversation::TYPE_TOURNAMENT_TEAM) {
            $title = $conversation->name ?? 'Tournament Team Chat';
            $subtitle = $conversation->tournamentTeam?->tournament?->name ?? 'Tournament Team Chat';
        } elseif ($conversation->type === ChatConversation::TYPE_GLOBAL) {
            $subtitle = 'All verified players';
        }

        return [
            'uuid' => $conversation->uuid,
            'type' => $conversation->type,
            'title' => $title,
            'subtitle' => $subtitle,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'team_id' => $conversation->team_id,
            'tournament_team_id' => $conversation->tournament_team_id,
            'is_unread' => $conversation->last_message_at !== null
                && ($participant?->last_read_at === null || $conversation->last_message_at->gt($participant->last_read_at)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messagePayload(ChatMessage $message): array
    {
        $message->loadMissing('user.profile');
        $user = $message->user;

        return [
            'id' => $message->id,
            'uuid' => $message->uuid,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'time' => $message->created_at?->format('H:i'),
            'user' => [
                'id' => $user?->id,
                'uuid' => $user?->uuid,
                'username' => $user?->username ?? 'Deleted Player',
                'initials' => strtoupper(substr($user?->username ?? 'PS', 0, 2)),
                'avatar_url' => $user?->profile?->avatar_url,
            ],
        ];
    }

    /**
     * @return Collection<int, ChatConversation>
     */
    public function userConversations(User $user): Collection
    {
        $global = $this->globalConversation($user);

        $participantConversationIds = ChatParticipant::query()
            ->where('user_id', $user->getKey())
            ->pluck('chat_conversation_id');

        /** @var Collection<int, ChatConversation> $conversations */
        $conversations = ChatConversation::query()
            ->with(['participants.user', 'team', 'tournamentTeam.tournament'])
            ->where(function ($query) use ($participantConversationIds, $global): void {
                $query->where('id', $global->getKey())
                    ->orWhereIn('id', $participantConversationIds);
            })
            ->orderByRaw('last_message_at is null')
            ->orderByDesc('last_message_at')
            ->orderBy('type')
            ->get();

        return $conversations;
    }

    private function ensureParticipant(ChatConversation $conversation, User $user, string $role): ChatParticipant
    {
        /** @var ChatParticipant $participant */
        $participant = ChatParticipant::query()->firstOrCreate(
            [
                'chat_conversation_id' => $conversation->getKey(),
                'user_id' => $user->getKey(),
            ],
            ['role' => $role]
        );

        return $participant;
    }

    private function authorizeTeamMember(Team $team, User $actor): void
    {
        $isMember = $team->members()
            ->where('user_id', $actor->getKey())
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            abort(403, 'Only active team members can open team chat.');
        }
    }

    private function pruneGlobalMessages(ChatConversation $conversation): void
    {
        if ($conversation->type !== ChatConversation::TYPE_GLOBAL) {
            return;
        }

        $idsToKeep = ChatMessage::query()
            ->where('chat_conversation_id', $conversation->getKey())
            ->latest('id')
            ->limit(100)
            ->pluck('id');

        ChatMessage::query()
            ->where('chat_conversation_id', $conversation->getKey())
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
