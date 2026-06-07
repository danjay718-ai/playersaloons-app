<?php

namespace App\Modules\Tournament\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_id
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property-read Tournament $tournament
 * @property-read Collection<int, Round> $rounds
 */
class Bracket extends Model
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
        'tournament_id',
        'generated_at',
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
            'generated_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the tournament.
     *
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the rounds in the bracket.
     *
     * @return HasMany<Round, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class);
    }
}
