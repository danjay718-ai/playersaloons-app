<?php

declare(strict_types=1);

namespace App\Modules\Stream\Models;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $tournament_id
 * @property int|null $game_id
 * @property string $provider
 * @property string $source_url
 * @property string|null $title
 * @property string|null $description
 * @property int $viewer_count
 * @property int $total_views
 * @property bool $is_public
 * @property bool $is_featured
 * @property bool $is_live
 * @property string|null $thumbnail_url
 * @property Carbon|null $taken_down_at
 * @property int|null $taken_down_by
 * @property string|null $takedown_reason
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Tournament|null $tournament
 * @property-read Game|null $game
 * @property-read User|null $takenDownBy
 */
class StreamChannel extends Model
{
    protected $fillable = [
        'user_id',
        'tournament_id',
        'game_id',
        'provider',
        'source_url',
        'title',
        'description',
        'viewer_count',
        'total_views',
        'is_public',
        'is_featured',
        'is_live',
        'thumbnail_url',
        'taken_down_at',
        'taken_down_by',
        'takedown_reason',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::created(function (StreamChannel $streamChannel): void {
            $streamChannel->logWriteActivity('stream_created', $streamChannel->getAttributes());
        });

        static::updated(function (StreamChannel $streamChannel): void {
            $changes = $streamChannel->getChanges();
            unset($changes['updated_at']);

            if ($changes !== []) {
                $streamChannel->logWriteActivity('stream_updated', $changes);
            }
        });

        static::deleted(function (StreamChannel $streamChannel): void {
            $streamChannel->logWriteActivity('stream_deleted', $streamChannel->getOriginal());
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function logWriteActivity(string $description, array $changes): void
    {
        activity()
            ->causedBy(Auth::user())
            ->performedOn($this)
            ->withProperties([
                'provider' => $this->provider,
                'user_id' => $this->user_id,
                'tournament_id' => $this->tournament_id,
                'game_id' => $this->game_id,
                'changes' => $changes,
            ])
            ->log($description);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'is_live' => 'boolean',
            'taken_down_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, StreamChannel>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Tournament, StreamChannel>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<Game, StreamChannel>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<User, StreamChannel>
     */
    public function takenDownBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_down_by');
    }

    /**
     * @return HasMany<StreamChatMessage, StreamChannel>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(StreamChatMessage::class);
    }

    /**
     * @return HasMany<StreamViewer, StreamChannel>
     */
    public function viewers(): HasMany
    {
        return $this->hasMany(StreamViewer::class);
    }

    /**
     * Sync viewer_count from actual active viewers (last 2 min).
     */
    public function syncViewerCount(): void
    {
        $count = $this->viewers()
            ->where('last_seen_at', '>=', now()->subMinutes(2))
            ->count();

        $this->update(['viewer_count' => $count]);
    }

    /**
     * Format viewer count for display (e.g. 1.2K).
     */
    public function formattedViewers(): string
    {
        $count = (int) $this->viewer_count;
        if ($count >= 1000000) {
            return round($count / 1000000, 1) . 'M';
        }
        if ($count >= 1000) {
            return round($count / 1000, 1) . 'K';
        }

        return (string) $count;
    }

    /**
     * Format total views for display.
     */
    public function formattedTotalViews(): string
    {
        $count = (int) $this->total_views;
        if ($count >= 1000000) {
            return round($count / 1000000, 1) . 'M';
        }
        if ($count >= 1000) {
            return round($count / 1000, 1) . 'K';
        }

        return (string) $count;
    }
}
