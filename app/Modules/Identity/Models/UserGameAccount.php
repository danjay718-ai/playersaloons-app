<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserGameAccount extends Model
{
    protected $fillable = [
        'user_id',
        'game_id',
        'platform_id',
        'game_id_value',
        'region',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class)->withTrashed();
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class)->withTrashed();
    }
}
