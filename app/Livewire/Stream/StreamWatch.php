<?php

declare(strict_types=1);

namespace App\Livewire\Stream;

use App\Modules\Stream\Events\StreamMessageDeleted;
use App\Modules\Stream\Events\StreamMessageSent;
use App\Modules\Stream\Events\StreamViewerCountUpdated;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Stream\Models\StreamChatMessage;
use App\Modules\Stream\Models\StreamViewer;
use App\Modules\Stream\Support\StreamEmbedService;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Throwable;

class StreamWatch extends Component
{
    public StreamChannel $streamChannel;

    public string $chatMessage = '';

    /** @var array<int, array<string, mixed>> */
    public array $recentMessages = [];

    public int $viewerCount = 0;

    public bool $isAdminView = false;

    public bool $canModerate = false;

    /** Used for admin: ID of message to confirm-delete */
    public ?int $deleteMessageId = null;

    private const CHAT_COLORS = [
        '#a78bfa', '#60a5fa', '#34d399', '#f472b6',
        '#fb923c', '#facc15', '#38bdf8', '#a3e635',
    ];

    public function mount(int $id): void
    {
        $user = Auth::user();

        $this->isAdminView = request()->is('admin/streams/*') || request()->is('admin/streams/'.$id);
        $this->canModerate = $user?->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'SUPPORT_AGENT']) ?? false;

        // Admins can view any public/taken-down stream; players only see public, not-taken-down
        $query = StreamChannel::query()->with(['user.profile', 'game.translations']);

        if ($this->canModerate) {
            $query->whereNotNull('user_id'); // Any player stream
        } else {
            $query->where('is_public', true)->whereNull('taken_down_at');
        }

        $this->streamChannel = $query->findOrFail($id);

        // Track viewer (players only)
        if (! $this->canModerate) {
            $this->trackViewer();
            $this->streamChannel->increment('total_views');
        }

        // Viewer count
        $this->viewerCount = $this->streamChannel->viewers()
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->count();

        // Load initial chat
        $this->loadMessages();
    }

    /**
     * Heartbeat every 30s — update viewer presence.
     */
    public function heartbeat(): void
    {
        if (! $this->canModerate) {
            $this->trackViewer();
        }

        $oldCount = $this->viewerCount;

        $this->viewerCount = $this->streamChannel->viewers()
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->count();

        $this->streamChannel->update(['viewer_count' => $this->viewerCount]);

        if ($oldCount !== $this->viewerCount) {
            $this->broadcastSafely(new StreamViewerCountUpdated($this->streamChannel->id, $this->viewerCount), 'viewer_count');
        }
    }

    public function loadMessages(): void
    {
        $this->recentMessages = StreamChatMessage::query()
            ->with(['user.profile', 'user.roles'])
            ->where('stream_channel_id', $this->streamChannel->id)
            ->where('is_deleted', false)
            ->latest()
            ->limit(60)
            ->get()
            ->reverse()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'user_id' => $msg->user_id,
                'username' => $msg->user->profile?->display_name ?? $msg->user->username,
                'message' => $msg->message,
                'color' => $msg->user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'SUPPORT_AGENT']) ? '#10b981' : $msg->color,
                'is_mod' => $msg->user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'SUPPORT_AGENT']),
                'time' => $msg->created_at?->format('H:i'),
            ])
            ->values()
            ->toArray();
    }

    public function sendMessage(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $mutedUsers = $this->streamChannel->metadata['muted_users'] ?? [];
        if (in_array($user->id, $mutedUsers)) {
            session()->flash('error', 'You are muted and cannot chat.');

            return;
        }

        $this->validate([
            'chatMessage' => 'required|string|max:300',
        ]);

        $message = trim($this->chatMessage);
        if ($message === '') {
            return;
        }

        $isMod = $user->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'SUPPORT_AGENT']);
        $colorIndex = $user->id % count(self::CHAT_COLORS);

        $msg = StreamChatMessage::create([
            'stream_channel_id' => $this->streamChannel->id,
            'user_id' => $user->id,
            'message' => $message,
            'color' => self::CHAT_COLORS[$colorIndex],
        ]);

        $messageData = [
            'id' => $msg->id,
            'user_id' => $user->id,
            'username' => $user->profile?->display_name ?? $user->username,
            'message' => $message,
            'color' => $isMod ? '#10b981' : self::CHAT_COLORS[$colorIndex],
            'is_mod' => $isMod,
            'time' => $msg->created_at?->format('H:i'),
        ];

        $this->broadcastSafely(new StreamMessageSent($this->streamChannel->id, $messageData), 'message_sent');

        $this->chatMessage = '';
        $this->recentMessages[] = $messageData;

        $this->dispatch('chat-updated');
    }

    public function muteUser(int $userId): void
    {
        if (! $this->canModerate) {
            abort(403);
        }

        $metadata = $this->streamChannel->metadata ?? [];
        $muted = $metadata['muted_users'] ?? [];
        if (! in_array($userId, $muted)) {
            $muted[] = $userId;
            $metadata['muted_users'] = $muted;
            $this->streamChannel->update(['metadata' => $metadata]);
        }

        session()->flash('success', 'User muted from chat.');
    }

    /** Admin: soft-delete a chat message */
    public function deleteMessage(int $messageId): void
    {
        if (! $this->canModerate) {
            abort(403);
        }

        StreamChatMessage::query()
            ->where('stream_channel_id', $this->streamChannel->id)
            ->findOrFail($messageId)
            ->forceFill(['is_deleted' => true, 'deleted_at' => now()])
            ->save();

        $this->deleteMessageId = null;
        $this->loadMessages();

        $this->broadcastSafely(new StreamMessageDeleted($this->streamChannel->id, $messageId), 'message_deleted');

        session()->flash('success', 'Message deleted.');
    }

    /** Admin: take the stream down */
    public function takeDown(): void
    {
        $this->requireModerator();

        $this->streamChannel->forceFill([
            'taken_down_at' => now(),
            'taken_down_by' => Auth::id(),
            'takedown_reason' => 'Taken down from stream viewer by admin.',
        ])->save();

        $this->streamChannel->refresh();

        activity()
            ->causedBy(Auth::user())
            ->performedOn($this->streamChannel)
            ->log('stream_taken_down');

        session()->flash('success', 'Stream taken down.');
    }

    /** Admin: restore a taken-down stream */
    public function restore(): void
    {
        $this->requireModerator();

        $this->streamChannel->forceFill([
            'taken_down_at' => null,
            'taken_down_by' => null,
            'takedown_reason' => null,
        ])->save();

        $this->streamChannel->refresh();

        activity()
            ->causedBy(Auth::user())
            ->performedOn($this->streamChannel)
            ->log('stream_restored');

        session()->flash('success', 'Stream restored.');
    }

    /** Admin: toggle featured flag */
    public function toggleFeatured(): void
    {
        $this->requireModerator();

        $this->streamChannel->update(['is_featured' => ! $this->streamChannel->is_featured]);
        $this->streamChannel->refresh();

        session()->flash('success', $this->streamChannel->is_featured ? 'Stream featured.' : 'Stream unfeatured.');
    }

    /** Admin: toggle live flag */
    public function toggleLive(): void
    {
        $this->requireModerator();

        $this->streamChannel->update(['is_live' => ! $this->streamChannel->is_live]);
        $this->streamChannel->refresh();

        session()->flash('success', $this->streamChannel->is_live ? 'Marked as LIVE.' : 'Marked as offline.');
    }

    /** Admin: hard-delete the stream channel entirely */
    public function deleteStream(): void
    {
        $admin = Auth::user();

        if (! $admin?->hasAnyRole(['SUPER_ADMIN', 'ADMIN'])) {
            abort(403);
        }

        $redirectUrl = $this->isAdminView ? '/admin/streams' : '/streams';
        $this->streamChannel->delete();

        $this->redirect($redirectUrl, navigate: true);
    }

    private function requireModerator(): void
    {
        if (! $this->canModerate) {
            abort(403);
        }
    }

    private function trackViewer(): void
    {
        $user = Auth::user();

        if ($user) {
            StreamViewer::updateOrCreate(
                ['stream_channel_id' => $this->streamChannel->id, 'user_id' => $user->id],
                ['last_seen_at' => now()]
            );
        }
    }

    private function broadcastSafely(object $event, string $action): void
    {
        try {
            $pendingBroadcast = broadcast($event);
            unset($pendingBroadcast);
        } catch (Throwable $exception) {
            Log::warning('Stream realtime broadcast failed.', [
                'stream_channel_id' => $this->streamChannel->id,
                'action' => $action,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    public function render(StreamEmbedService $streams)
    {
        // Force admin layout if user is moderator
        if ($this->isAdminView && ! $this->canModerate) {
            abort(403);
        }

        $stream = $streams->streamForChannel($this->streamChannel);

        if ($stream === null) {
            abort(404, 'Stream embed could not be resolved.');
        }

        $layout = $this->isAdminView
            ? 'components.layouts.admin'
            : 'components.layouts.dashboard';

        $layoutData = $this->isAdminView
            ? [
                'title' => ($this->streamChannel->title ?? 'Stream').' | Admin',
                'admin_title' => 'Stream Viewer',
            ]
            : [
                'title' => ($this->streamChannel->title ?? 'Stream').' | PlayerSaloons',
                'dashboard_title' => 'LIVE STREAM',
            ];

        // Related content belongs in the component rather than the Blade view.
        // Both queries are bounded and eager-load every relation used by cards.
        $gameStreams = collect();
        $gameCompetitions = collect();
        if (! $this->isAdminView && $this->streamChannel->game_id !== null) {
            $gameStreams = StreamChannel::query()
                ->with('user.profile')
                ->where('game_id', $this->streamChannel->game_id)
                ->where('is_public', true)
                ->whereNull('taken_down_at')
                ->whereKeyNot($this->streamChannel->id)
                ->orderByDesc('viewer_count')
                ->limit(6)
                ->get();

            $gameCompetitions = Tournament::query()
                ->where('game_id', $this->streamChannel->game_id)
                ->whereIn('status', [
                    TournamentStatus::PUBLISHED,
                    TournamentStatus::REGISTRATION_OPEN,
                    TournamentStatus::REGISTRATION_CLOSED,
                    TournamentStatus::CHECKIN_OPEN,
                    TournamentStatus::CHECKIN_CLOSED,
                    TournamentStatus::BRACKET_GENERATED,
                    TournamentStatus::ONGOING,
                ])
                ->orderBy('start_at')
                ->limit(5)
                ->get();
        }

        return view('livewire.stream.stream-watch', [
            'stream' => $stream,
            'embedUrl' => $stream['embed_url'],
            'streamerName' => $this->streamChannel->user?->profile?->display_name
                ?? $this->streamChannel->user?->username
                ?? 'Stream',
            'gameStreams' => $gameStreams,
            'gameCompetitions' => $gameCompetitions,
        ])->layout($layout, $layoutData);
    }
}
