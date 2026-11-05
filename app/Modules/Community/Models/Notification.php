<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'title',
        'message',
        'action_url',
        'read_at',
        'chat_conversation_id',
        'chat_message_id',
        'dismissed_at',
    ];

    public function availableActionUrl(): ?string
    {
        $url = $this->action_url;
        if ($url === null || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        if (preg_match('#^/tournaments/([^/]+)/view$#', (string) parse_url($url, PHP_URL_PATH), $matches)) {
            $tournament = Tournament::query()->where('uuid', $matches[1])->first();
            if ($tournament === null || in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::CANCELLED, TournamentStatus::REFUNDED], true)
                || ((int) $tournament->workflow_version === 2 && ! config('features.tournament_v2.enabled'))) {
                return null;
            }
        }

        return $url;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'chat_conversation_id' => 'integer',
            'chat_message_id' => 'integer',
        ];
    }

    /**
     * Get the user who received this notification.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
