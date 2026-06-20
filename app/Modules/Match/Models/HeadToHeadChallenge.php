<?php

declare(strict_types=1);

namespace App\Modules\Match\Models;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HeadToHeadChallenge extends Model
{
    protected $fillable = [
        'uuid',
        'creator_user_id',
        'game_id',
        'platform_id',
        'stake_amount',
        'status',
        'creator_game_handle',
        'region',
        'match_timer_minutes',
        'expires_at',
        'matched_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'stake_amount' => 'decimal:2',
            'status' => HeadToHeadChallengeStatus::class,
            'match_timer_minutes' => 'integer',
            'expires_at' => 'datetime',
            'matched_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function match(): HasOne
    {
        return $this->hasOne(HeadToHeadMatch::class, 'challenge_id');
    }
}
