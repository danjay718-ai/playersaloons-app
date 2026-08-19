<?php

declare(strict_types=1);

namespace App\Modules\Match\Models;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeadToHeadRating extends Model
{
    protected $fillable = ['user_id', 'game_id', 'rating', 'wins', 'losses'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'wins' => 'integer', 'losses' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class)->withTrashed();
    }
}
