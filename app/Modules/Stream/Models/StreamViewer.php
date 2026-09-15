<?php

declare(strict_types=1);

namespace App\Modules\Stream\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $stream_channel_id
 * @property int|null $user_id
 * @property string|null $session_token
 * @property Carbon $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read StreamChannel $streamChannel
 */
class StreamViewer extends Model
{
    protected $fillable = [
        'stream_channel_id',
        'user_id',
        'session_token',
        'last_seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, StreamViewer>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<StreamChannel, StreamViewer>
     */
    public function streamChannel(): BelongsTo
    {
        return $this->belongsTo(StreamChannel::class)->withTrashed();
    }
}
