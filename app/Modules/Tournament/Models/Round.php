<?php

namespace App\Modules\Tournament\Models;

use App\Modules\Match\Models\GameMatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $bracket_id
 * @property int $round_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Modules\Tournament\Models\Bracket $bracket
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Match\Models\GameMatch> $matches
 */
class Round extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bracket_id',
        'round_number',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the bracket.
     *
     * @return BelongsTo<Bracket, $this>
     */
    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    /**
     * Get matches in the round.
     *
     * @return HasMany<GameMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'round_id');
    }
}
