<?php

namespace App\Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $game_id
 * @property string $locale
 * @property string $name
 * @property string $description
 */
class GameTranslation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'game_id',
        'locale',
        'name',
        'description',
    ];

    /**
     * Get the game this translation belongs to.
     *
     * @return BelongsTo<Game, GameTranslation>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
