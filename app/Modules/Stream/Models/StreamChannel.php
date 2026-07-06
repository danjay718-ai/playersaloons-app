<?php

declare(strict_types=1);

namespace App\Modules\Stream\Models;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property bool $is_public
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
        'is_public',
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
}
