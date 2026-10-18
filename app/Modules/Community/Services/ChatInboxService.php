<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Modules\Community\Events\ChatInboxUpdated;
use App\Modules\Community\Models\ChatConversation;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Community\Models\ChatParticipant;
use App\Modules\Community\Models\Notification;
use App\Modules\Community\Models\NotificationPreference;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class ChatInboxService
{
    public function summary(User $user): array
    {
        $conversations = ChatConversation::query()->where('type', ChatConversation::TYPE_DIRECT)
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id)->where('notifications_muted', false))
            ->whereHas('messages', function ($query) use ($user): void {
                $query->where('user_id', '!=', $user->id)->where('id', '>', function ($checkpoint) use ($user): void {
                    $checkpoint->select('last_read_message_id')->from('chat_participants')
                        ->whereColumn('chat_participants.chat_conversation_id', 'chat_messages.chat_conversation_id')->where('user_id', $user->id);
                });
            })
            ->with(['participants.user'])->get();

        return [
            'unread_count' => $conversations->count(),
            'conversations' => $conversations->map(fn ($conversation) => [
                'uuid' => $conversation->uuid,
                'sender' => $conversation->participants->firstWhere('user_id', '!=', $user->id)?->user->username ?? __('Player'),
                'url' => '/chat?conversation='.$conversation->uuid,
            ])->values()->all(),
        ];
    }

    /** Called inside the send transaction while its conversation is locked. */
    public function recordMessage(ChatConversation $conversation, ChatMessage $message): void
    {
        if ($conversation->type !== ChatConversation::TYPE_DIRECT) {
            return;
        }
        foreach ($conversation->participants()->with('user')->where('user_id', '!=', $message->user_id)->get() as $participant) {
            $recipient = $participant->user;
            $preferences = NotificationPreference::query()->where('user_id', $recipient->id)->first();
            $eligible = ! $participant->notifications_muted;
            if ($eligible && ($preferences->in_app_enabled ?? true)) {
                $notification = Notification::query()->firstOrNew(['user_id' => $recipient->id, 'chat_conversation_id' => $conversation->id]);
                if (! $notification->exists) {
                    $notification->uuid = Str::uuid()->toString();
                }
                $notification->fill([
                    'type' => 'chat.direct',
                    'title' => __('New direct message'),
                    'message' => __(':player sent you a message.', ['player' => $message->user->username ?? __('Player')]),
                    'action_url' => '/chat?conversation='.$conversation->uuid,
                    'chat_message_id' => $message->id, 'read_at' => null, 'dismissed_at' => null,
                ])->forceFill(['created_at' => now()])->save();
            }
            $this->notify($recipient, $conversation, $message->id, $eligible && ($preferences->realtime_enabled ?? true));
        }
    }

    public function acknowledge(ChatConversation $conversation, User $user, int $messageId): void
    {
        abort_unless(app(ChatService::class)->canAccessConversation($conversation, $user), 403);
        abort_unless($conversation->messages()->whereKey($messageId)->exists(), 422, 'Message does not belong to this conversation.');
        DB::transaction(function () use ($conversation, $user, $messageId): void {
            ChatConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $participant = ChatParticipant::query()->firstOrCreate(
                ['chat_conversation_id' => $conversation->id, 'user_id' => $user->id], ['role' => 'member'],
            );
            if ($messageId > $participant->last_read_message_id) {
                $participant->update(['last_read_message_id' => $messageId, 'last_read_at' => now()]);
            }
            $user->notifications()->where('chat_conversation_id', $conversation->id)
                ->where('chat_message_id', '<=', $participant->last_read_message_id)->whereNull('read_at')->update(['read_at' => now()]);
            $this->notify($user, $conversation);
        });
    }

    public function mute(ChatConversation $conversation, User $user, bool $muted): void
    {
        abort_unless(app(ChatService::class)->canAccessConversation($conversation, $user), 403);
        DB::transaction(function () use ($conversation, $user, $muted): void {
            ChatConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            ChatParticipant::query()->updateOrCreate(
                ['chat_conversation_id' => $conversation->id, 'user_id' => $user->id], ['notifications_muted' => $muted],
            );
            if ($muted) {
                $user->notifications()->where('chat_conversation_id', $conversation->id)->whereNull('dismissed_at')->update(['dismissed_at' => now()]);
            }
            $this->notify($user, $conversation);
        });
    }

    private function notify(User $user, ChatConversation $conversation, ?int $messageId = null, bool $alert = false): void
    {
        $event = new ChatInboxUpdated($user->uuid, $conversation->uuid, $messageId, $alert);
        DB::afterCommit(function () use ($event): void {
            try {
                $pending = broadcast($event);
                unset($pending);
            } catch (Throwable $exception) {
                Log::warning('Chat inbox broadcast failed.', ['user_uuid' => $event->userUuid, 'exception' => $exception->getMessage()]);
            }
        });
    }
}
