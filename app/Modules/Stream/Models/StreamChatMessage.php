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
 * @property int $user_id
 * @property string $message
 * @property string $color
 * @property bool $is_deleted
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read StreamChannel $streamChannel
 */
class StreamChatMessage extends Model
{
    protected $fillable = [
        'stream_channel_id',
        'user_id',
        'message',
        'color',
        'is_deleted',
        'deleted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_deleted' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, StreamChatMessage>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<StreamChannel, StreamChatMessage>
     */
    public function streamChannel(): BelongsTo
    {
        return $this->belongsTo(StreamChannel::class);
    }
}
