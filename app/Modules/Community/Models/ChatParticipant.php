<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_conversation_id
 * @property int $user_id
 * @property string $role
 * @property Carbon|null $last_read_at
 * @property Carbon|null $muted_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChatConversation $conversation
 * @property-read User $user
 */
class ChatParticipant extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'chat_conversation_id',
        'user_id',
        'role',
        'last_read_at',
        'muted_until',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'muted_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ChatConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
