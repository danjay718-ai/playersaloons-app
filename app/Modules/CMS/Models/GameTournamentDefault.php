<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GameTournamentDefault extends Model
{
    protected $fillable = [
        'game_id',
        'default_platform_id',
        'tournament_banner_path',
        'description',
        'rules',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function defaultPlatform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'default_platform_id');
    }
}
