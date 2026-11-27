<?php

namespace App\Modules\Community\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string $message
 * @property string $display_type
 * @property string $target_surface
 * @property string|null $badge_label
 * @property string $media_type
 * @property string|null $media_url
 * @property string|null $cta_label
 * @property string|null $cta_url
 * @property bool $is_video_only
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class BroadcastMessage extends Model
{
    use SoftDeletes;

    public const DISPLAY_FEED = 'feed';

    public const DISPLAY_POPUP = 'popup_modal';

    public const DISPLAY_BOTH = 'both';

    public const SURFACE_HOMEPAGE = 'homepage';

    public const SURFACE_PLAYER_DASHBOARD = 'player_dashboard';

    public const SURFACE_BOTH = 'both';

    public const MEDIA_NONE = 'none';

    public const MEDIA_IMAGE = 'image';

    public const MEDIA_VIDEO = 'video';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'title',
        'message',
        'display_type',
        'target_surface',
        'badge_label',
        'media_type',
        'media_url',
        'cta_label',
        'cta_url',
        'is_video_only',
        'starts_at',
        'ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_video_only' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(fn (Builder $q): Builder => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q): Builder => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActivePopupsForSurface(Builder $query, string $surface): Builder
    {
        return $query
            ->activeNow()
            ->whereIn('display_type', [self::DISPLAY_POPUP, self::DISPLAY_BOTH])
            ->whereIn('target_surface', [$surface, self::SURFACE_BOTH])
            ->latest('created_at');
    }

    public function resolvedMediaUrl(): ?string
    {
        $raw = trim((string) ($this->media_url ?? ''));
        if ($raw === '' || $this->media_type === self::MEDIA_NONE) {
            return null;
        }

        if (str_starts_with($raw, 'announcements/')) {
            return Storage::disk('public')->url($raw);
        }

        return self::sanitizeUrl($raw);
    }

    public function embedVideoUrl(): ?string
    {
        if ($this->media_type !== self::MEDIA_VIDEO) {
            return null;
        }

        $url = $this->resolvedMediaUrl();
        if ($url === null) {
            return null;
        }

        if (preg_match('/(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/', $url, $matches) === 1) {
            return 'https://www.youtube-nocookie.com/embed/'.$matches[1].'?rel=0&playsinline=1';
        }

        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches) === 1) {
            return 'https://player.vimeo.com/video/'.$matches[1];
        }

        return null;
    }

    public function safeCtaUrl(): ?string
    {
        return self::sanitizeUrl($this->cta_url);
    }

    public static function sanitizeUrl(?string $url): ?string
    {
        $raw = trim((string) ($url ?? ''));
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '/') && ! str_starts_with($raw, '//')) {
            return $raw;
        }

        $scheme = strtolower((string) parse_url($raw, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            return $raw;
        }

        return null;
    }

    /**
     * @return list<array{
     *     uuid: string,
     *     title: string,
     *     message: string,
     *     badge_label: string,
     *     media_type: string,
     *     media_url: string|null,
     *     embed_url: string|null,
     *     is_video_only: bool,
     *     is_media_only: bool,
     *     cta_label: string|null,
     *     cta_url: string|null,
     *     published_at: string|null
     * }>
     */
    public static function activePopupsPayload(string $surface): array
    {
        return Cache::remember(
            self::popupCacheKey($surface),
            now()->addMinute(),
            fn (): array => self::query()
                ->activePopupsForSurface($surface)
                ->limit(5)
                ->get()
                ->map(function (self $item): array {
                    $mediaType = $item->media_type ?: self::MEDIA_NONE;
                    $mediaUrl = $item->resolvedMediaUrl();
                    $embedUrl = $item->embedVideoUrl();
                    $hasMedia = $mediaType !== self::MEDIA_NONE && ($mediaUrl !== null || $embedUrl !== null);
                    $hasMessage = trim($item->message) !== '';

                    return [
                        'uuid' => $item->uuid,
                        'title' => $item->title,
                        'message' => $item->message,
                        'badge_label' => trim((string) ($item->badge_label ?? '')) ?: __("What's New"),
                        'media_type' => $mediaType,
                        'media_url' => $mediaUrl,
                        'embed_url' => $embedUrl,
                        'is_video_only' => (bool) $item->is_video_only && $mediaType === self::MEDIA_VIDEO,
                        'is_media_only' => $hasMedia && ((bool) $item->is_video_only || ! $hasMessage),
                        'cta_label' => trim((string) ($item->cta_label ?? '')) ?: null,
                        'cta_url' => $item->safeCtaUrl(),
                        'published_at' => $item->created_at?->diffForHumans(),
                    ];
                })
                ->all()
        );
    }

    public static function clearCaches(): void
    {
        Cache::forget('player-dashboard:announcements:v3');
        Cache::forget(self::popupCacheKey(self::SURFACE_HOMEPAGE));
        Cache::forget(self::popupCacheKey(self::SURFACE_PLAYER_DASHBOARD));
    }

    public static function popupCacheKey(string $surface): string
    {
        return 'announcement-popups:v1:'.$surface;
    }
}
