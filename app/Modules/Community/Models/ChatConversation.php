<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Modules\Team\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $type
 * @property string $scope_key
 * @property string|null $name
 * @property int|null $team_id
 * @property int|null $created_by_user_id
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 * @property-read Collection<int, ChatParticipant> $participants
 * @property-read Collection<int, ChatMessage> $messages
 */
class ChatConversation extends Model
{
    public const TYPE_GLOBAL = 'global';
    public const TYPE_DIRECT = 'direct';
    public const TYPE_TEAM = 'team';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'type',
        'scope_key',
        'name',
        'team_id',
        'created_by_user_id',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<ChatParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
