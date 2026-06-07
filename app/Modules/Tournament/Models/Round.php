<?php

namespace App\Modules\Tournament\Models;

use App\Modules\Match\Models\GameMatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * @return BelongsTo<Bracket, Round>
     */
    public function bracket(): BelongsTo
    {
        return $this->belongsTo(Bracket::class);
    }

    /**
     * Get matches in the round.
     *
     * @return HasMany<GameMatch, Round>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'round_id');
    }
}
