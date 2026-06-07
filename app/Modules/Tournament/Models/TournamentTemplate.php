<?php

namespace App\Modules\Tournament\Models;

use App\Modules\CMS\Models\Game;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TournamentTemplate extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'game_id',
        'name',
        'format',
        'max_participants',
        'min_participants',
        'entry_fee',
        'prize_model',
        'checkin_minutes',
        'is_recurring',
        'settings_json',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_participants' => 'integer',
            'min_participants' => 'integer',
            'entry_fee' => 'decimal:2',
            'checkin_minutes' => 'integer',
            'is_recurring' => 'boolean',
            'settings_json' => 'array',
        ];
    }

    /**
     * Get the game this template belongs to.
     *
     * @return BelongsTo<Game, TournamentTemplate>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get prizes configured for this template.
     *
     * @return HasMany<TournamentTemplatePrize, TournamentTemplate>
     */
    public function prizes(): HasMany
    {
        return $this->hasMany(TournamentTemplatePrize, 'template_id');
    }
}
