<?php

namespace App\Modules\Tournament\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentTemplatePrize extends Model
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
        'template_id',
        'position',
        'amount',
        'percentage',
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
            'position' => 'integer',
            'amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the template this prize belongs to.
     *
     * @return BelongsTo<TournamentTemplate, TournamentTemplatePrize>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TournamentTemplate::class, 'template_id');
    }
}
