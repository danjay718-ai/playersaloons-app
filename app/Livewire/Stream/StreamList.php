<?php

declare(strict_types=1);

namespace App\Livewire\Stream;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Stream\Support\StreamEmbedService;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Closure;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class StreamList extends Component
{
    use WithFileUploads, WithPagination;

    // ── Player stream form ──────────────────────────────────────────────
    public ?string $streamTitle = null;

    public ?string $streamDescription = null;

    public ?string $youtube_stream_url = null;

    public ?string $twitch_stream_url = null;

    public ?string $facebook_stream_url = null;

    public bool $is_public = true;

    public ?int $game_id = null;

    public ?string $takedownReason = null;

    public $streamThumbnail = null;

    public ?string $thumbnailUrl = null;

    // ── Browse tab state ────────────────────────────────────────────────
    /** 'all' | 'game:{id}' | 'tournaments' */
    public string $activeTab = 'all';

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('PLAYER')) {
            return;
        }

        $playerStreams = StreamChannel::query()
            ->where('user_id', $user->getKey())
            ->whereNull('tournament_id')
            ->get()
            ->keyBy('provider');

        if ($playerStreams->isEmpty()) {
            return;
        }

        /** @var StreamChannel|null $firstStream */
        $firstStream = $playerStreams->first();
        $this->streamTitle = $firstStream?->title;
        $this->streamDescription = $firstStream?->description;
        $this->game_id = $firstStream?->game_id;
        $this->is_public = (bool) ($firstStream?->is_public ?? true);
        $this->youtube_stream_url = $playerStreams->get('youtube')?->source_url;
        $this->twitch_stream_url = $playerStreams->get('twitch')?->source_url;
        $this->facebook_stream_url = $playerStreams->get('facebook')?->source_url;
        $this->thumbnailUrl = $firstStream?->thumbnail_url;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function savePlayerStream(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('PLAYER')) {
            abort(403);
        }

        $this->validate([
            'streamTitle' => 'nullable|string|max:80',
            'streamDescription' => 'nullable|string|max:500',
            'game_id' => 'nullable|integer|exists:games,id',
            'youtube_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('youtube')],
            'twitch_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('twitch')],
            'facebook_stream_url' => ['nullable', 'url:https', 'max:255', $this->streamUrlRule('facebook')],
            'is_public' => 'boolean',
            'streamThumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp',
        ]);

        if (! $this->hasAnyStreamUrl()) {
            $this->addError('youtube_stream_url', 'Add at least one supported stream URL.');

            return;
        }

        $hasTakenDownStream = StreamChannel::query()
            ->where('user_id', $user->getKey())
            ->whereNull('tournament_id')
            ->whereNotNull('taken_down_at')
            ->exists();

        if ($hasTakenDownStream) {
            session()->flash('error', 'Your stream is currently taken down by admin review.');

            return;
        }

        if ($this->streamThumbnail) {
            $path = $this->streamThumbnail->store('streams/thumbnails/'.$user->getKey(), 'public');
            $this->thumbnailUrl = '/storage/'.$path;
        }

        $this->syncPlayerStreamChannel($user, 'youtube', $this->youtube_stream_url);
        $this->syncPlayerStreamChannel($user, 'twitch', $this->twitch_stream_url);
        $this->syncPlayerStreamChannel($user, 'facebook', $this->facebook_stream_url);

        session()->flash('success', 'Stream settings saved.');
    }

    public function takeDownPlayerStream(int $streamChannelId): void
    {
        $admin = Auth::user();

        if (! $admin || ! $admin->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'SUPPORT_AGENT'])) {
            abort(403);
        }

        $this->validate([
            'takedownReason' => 'nullable|string|max:500',
        ]);

        $streamChannel = StreamChannel::query()->whereNotNull('user_id')->findOrFail($streamChannelId);
        $streamChannel->forceFill([
            'taken_down_at' => now(),
            'taken_down_by' => $admin->getKey(),
            'takedown_reason' => $this->nullableText($this->takedownReason) ?? 'Taken down by admin review.',
        ])->save();

        activity()
            ->causedBy($admin)
            ->performedOn($streamChannel)
            ->withProperties([
                'provider' => $streamChannel->provider,
                'streamer_user_id' => $streamChannel->user_id,
                'reason' => $streamChannel->takedown_reason,
            ])
            ->log('stream_taken_down');

        $this->takedownReason = null;
        session()->flash('success', 'Player stream taken down.');
    }

    public function restorePlayerStream(int $streamChannelId): void
    {
        $admin = Auth::user();

        if (! $admin || ! $admin->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'SUPPORT_AGENT'])) {
            abort(403);
        }

        $streamChannel = StreamChannel::query()->whereNotNull('user_id')->findOrFail($streamChannelId);
        $streamChannel->forceFill([
            'taken_down_at' => null,
            'taken_down_by' => null,
            'takedown_reason' => null,
        ])->save();

        activity()
            ->causedBy($admin)
            ->performedOn($streamChannel)
            ->withProperties([
                'provider' => $streamChannel->provider,
                'streamer_user_id' => $streamChannel->user_id,
            ])
            ->log('stream_restored');

        session()->flash('success', 'Player stream restored.');
    }

    public function toggleFeature(int $streamChannelId): void
    {
        $admin = Auth::user();

        if (! $admin || ! $admin->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR'])) {
            abort(403);
        }

        $streamChannel = StreamChannel::query()->whereNotNull('user_id')->findOrFail($streamChannelId);
        $streamChannel->update(['is_featured' => ! $streamChannel->is_featured]);

        activity()
            ->causedBy($admin)
            ->performedOn($streamChannel)
            ->withProperties(['is_featured' => $streamChannel->fresh()?->is_featured])
            ->log('stream_feature_toggled');

        session()->flash('success', $streamChannel->fresh()?->is_featured ? 'Stream featured.' : 'Stream unfeatured.');
    }

    public function toggleLive(int $streamChannelId): void
    {
        $admin = Auth::user();

        if (! $admin || ! $admin->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR'])) {
            abort(403);
        }

        $streamChannel = StreamChannel::query()->whereNotNull('user_id')->findOrFail($streamChannelId);
        $streamChannel->update(['is_live' => ! $streamChannel->is_live]);

        activity()
            ->causedBy($admin)
            ->performedOn($streamChannel)
            ->withProperties(['is_live' => $streamChannel->fresh()?->is_live])
            ->log('stream_live_toggled');

        session()->flash('success', $streamChannel->fresh()?->is_live ? 'Stream marked as LIVE.' : 'Stream marked as offline.');
    }

    public function render(StreamEmbedService $streams)
    {
        $user = Auth::user();
        $isAdminView = request()->is('admin/streams');
        $canModerate = $user?->hasAnyRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'SUPPORT_AGENT']) ?? false;

        if ($isAdminView && ! $canModerate) {
            abort(403);
        }

        // ── Featured streams (public, not taken down, marked featured) ──────
        $featuredStreams = StreamChannel::query()
            ->with(['user.profile', 'game.translations'])
            ->whereNotNull('user_id')
            ->whereNull('tournament_id')
            ->where('is_public', true)
            ->whereNull('taken_down_at')
            ->where('is_featured', true)
            ->latest()
            ->limit(10)
            ->get();

        // If no featured, fall back to most-viewed
        if ($featuredStreams->isEmpty()) {
            $featuredStreams = StreamChannel::query()
                ->with(['user.profile', 'game.translations'])
                ->whereNotNull('user_id')
                ->whereNull('tournament_id')
                ->where('is_public', true)
                ->whereNull('taken_down_at')
                ->orderByDesc('viewer_count')
                ->latest()
                ->limit(5)
                ->get();
        }

        // ── All public player streams for browse ────────────────────────────
        $ownStreams = $user?->hasRole('PLAYER')
            ? StreamChannel::query()
                ->where('user_id', $user->getKey())
                ->whereNull('tournament_id')
                ->get()
            : collect();

        $playerStreamQuery = StreamChannel::query()
            ->with(['user.profile', 'takenDownBy', 'game.translations'])
            ->whereNotNull('user_id')
            ->whereNull('tournament_id')
            ->when(! $canModerate, function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where(function ($query) {
                        $query->where('is_public', true)->whereNull('taken_down_at');
                    });

                    if ($user !== null) {
                        $query->orWhere('user_id', $user->getKey());
                    }
                });
            })
            ->when(str_starts_with($this->activeTab, 'game:'), function ($query): void {
                $query->where('game_id', (int) substr($this->activeTab, 5));
            })
            ->orderByRaw('case when taken_down_at is null then 0 else 1 end')
            ->orderByDesc('viewer_count')
            ->latest();

        $playerStreams = $playerStreamQuery->paginate(12);

        // ── Games with active streams ────────────────────────────────────────
        $games = Game::query()
            ->with('translations')
            ->where('is_active', true)
            ->whereHas('streamChannels', function ($q) {
                $q->whereNotNull('user_id')
                    ->where('is_public', true)
                    ->whereNull('taken_down_at');
            })
            ->orderBy('slug')
            ->get();

        // The game filter is applied in SQL before pagination.
        $browsedStreams = $playerStreams;

        // ── Tournament broadcasts ───────────────────────────────────────────
        $tournaments = Tournament::query()
            ->with('game.translations')
            ->whereNotIn('status', [
                TournamentStatus::DRAFT->value,
                TournamentStatus::CANCELLED->value,
                TournamentStatus::REFUNDED->value,
            ])
            ->whereHas('streamChannels')
            ->with('streamChannels')
            ->orderByRaw("case when status = 'ONGOING' then 0 when status = 'BRACKET_GENERATED' then 1 when status = 'COMPLETED' then 3 else 2 end")
            ->orderBy('start_at')
            ->limit(12)
            ->get();

        // ── Games with translations for form dropdown ───────────────────────
        $allGames = Game::query()
            ->with('translations')
            ->where('is_active', true)
            ->orderBy('slug')
            ->get();

        return view('livewire.stream.stream-list', [
            'featuredStreams' => $featuredStreams,
            'playerStreams' => $playerStreams,
            'ownStreams' => $ownStreams,
            'browsedStreams' => $browsedStreams,
            'tournaments' => $tournaments,
            'games' => $games,
            'allGames' => $allGames,
            'streamService' => $streams,
            'canModerateStreams' => $canModerate,
            'isAdminView' => $isAdminView,
        ])->layout($isAdminView ? 'components.layouts.admin' : 'components.layouts.dashboard', [
            'title' => 'Streams | PlayerSaloons',
            'dashboard_title' => 'LIVE STREAMS',
            'admin_title' => 'Stream Moderation',
        ]);
    }

    private function streamUrlRule(string $provider): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($provider): void {
            if (! app(StreamEmbedService::class)->isValidProviderUrl($provider, is_string($value) ? $value : null)) {
                $fail('The '.$attribute.' must be a valid supported '.$provider.' stream URL.');
            }
        };
    }

    private function hasAnyStreamUrl(): bool
    {
        return $this->nullableText($this->youtube_stream_url) !== null
            || $this->nullableText($this->twitch_stream_url) !== null
            || $this->nullableText($this->facebook_stream_url) !== null;
    }

    private function nullableText(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function syncPlayerStreamChannel(User $user, string $provider, ?string $url): void
    {
        $url = $this->nullableText($url);
        $existing = StreamChannel::query()
            ->where('user_id', $user->getKey())
            ->whereNull('tournament_id')
            ->where('provider', $provider)
            ->first();

        if ($url === null) {
            if ($existing !== null) {
                $properties = [
                    'provider' => $existing->provider,
                    'source_url' => $existing->source_url,
                    'title' => $existing->title,
                    'is_public' => $existing->is_public,
                ];

                $existing->delete();

                activity()
                    ->causedBy($user)
                    ->performedOn($existing)
                    ->withProperties($properties)
                    ->log('stream_removed');
            }

            return;
        }

        $streamChannel = $existing ?? new StreamChannel([
            'user_id' => $user->getKey(),
            'provider' => $provider,
        ]);

        $streamChannel->fill([
            'source_url' => $url,
            'title' => $this->nullableText($this->streamTitle),
            'description' => $this->nullableText($this->streamDescription),
            'game_id' => $this->game_id,
            'is_public' => $this->is_public,
            'thumbnail_url' => $this->thumbnailUrl,
        ]);

        $changes = $streamChannel->getDirty();

        if ($changes === []) {
            return;
        }

        $isNew = ! $streamChannel->exists;
        $streamChannel->save();

        activity()
            ->causedBy($user)
            ->performedOn($streamChannel)
            ->withProperties([
                'provider' => $provider,
                'changes' => $changes,
            ])
            ->log($isNew ? 'stream_created' : 'stream_updated');
    }
}
