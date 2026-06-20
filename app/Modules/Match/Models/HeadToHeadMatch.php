<?php

declare(strict_types=1);

namespace App\Modules\Match\Models;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\HeadToHeadMatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeadToHeadMatch extends Model
{
    protected $fillable = [
        'uuid',
        'challenge_id',
        'creator_user_id',
        'opponent_user_id',
        'game_id',
        'platform_id',
        'stake_amount',
        'status',
        'creator_game_handle',
        'opponent_game_handle',
        'region',
        'match_timer_minutes',
        'winner_user_id',
        'result_submitted_by',
        'result_notes',
        'started_at',
        'result_submitted_at',
        'confirmation_due_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'stake_amount' => 'decimal:2',
            'status' => HeadToHeadMatchStatus::class,
            'match_timer_minutes' => 'integer',
            'started_at' => 'datetime',
            'result_submitted_at' => 'datetime',
            'confirmation_due_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(HeadToHeadChallenge::class, 'challenge_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_user_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
