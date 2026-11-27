<?php

declare(strict_types=1);

namespace App\Modules\Match\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracks one external gameplay provider session per match attempt.
 *
 * @property int $id
 * @property string $uuid
 * @property int $match_id
 * @property int|null $match_attempt_id
 * @property int $attempt_number
 * @property string $provider
 * @property string $provider_variant
 * @property array<string, mixed>|null $expected_settings_json
 * @property string|null $player_a_external_user_id
 * @property string|null $player_b_external_user_id
 * @property string|null $external_game_id
 * @property string|null $play_url
 * @property string $status
 * @property string|null $provider_status
 * @property string|null $provider_winner_side
 * @property Carbon|null $creation_started_at
 * @property Carbon|null $last_polled_at
 * @property Carbon|null $next_poll_at
 * @property int $poll_attempts
 * @property string|null $last_error
 * @property array<string, mixed>|null $payload_json
 * @property-read GameMatch $match
 * @property-read MatchAttempt|null $attempt
 */
class ExternalMatchGame extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CREATING = 'creating';

    public const STATUS_CREATED = 'created';

    public const STATUS_RECONCILING = 'reconciling';

    public const STATUS_NEEDS_ADMIN_REVIEW = 'needs_admin_review';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DRAW = 'draw';

    public const STATUS_ABORTED = 'aborted';

    public const STATUS_FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'match_id',
        'match_attempt_id',
        'attempt_number',
        'provider',
        'provider_variant',
        'expected_settings_json',
        'player_a_external_user_id',
        'player_b_external_user_id',
        'external_game_id',
        'play_url',
        'status',
        'provider_status',
        'provider_winner_side',
        'creation_started_at',
        'last_polled_at',
        'next_poll_at',
        'poll_attempts',
        'last_error',
        'payload_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'poll_attempts' => 'integer',
            'expected_settings_json' => 'array',
            'payload_json' => 'array',
            'creation_started_at' => 'datetime',
            'last_polled_at' => 'datetime',
            'next_poll_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<GameMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /**
     * @return BelongsTo<MatchAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(MatchAttempt::class, 'match_attempt_id');
    }
}
