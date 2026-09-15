<?php

declare(strict_types=1);

namespace App\Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Defaults used only when an admin creates a platform-managed H2H schedule. */
final class GameHeadToHeadDefault extends Model
{
    protected $fillable = [
        'game_id',
        'default_platform_id',
        'head_to_head_banner_path',
        'description',
        'rules',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class)->withTrashed();
    }

    public function defaultPlatform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'default_platform_id')->withTrashed();
    }
}
