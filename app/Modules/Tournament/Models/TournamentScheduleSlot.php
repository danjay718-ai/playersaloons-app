<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TournamentScheduleSlot extends Model
{
    protected $fillable = [
        'uuid',
        'tournament_template_id',
        'identity_key',
        'label',
        'local_start_time',
        'schedule_start_at',
        'schedule_end_at',
        'day_of_week',
        'day_of_month',
        'overrides_json',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'schedule_start_at' => 'datetime',
            'schedule_end_at' => 'datetime',
            'day_of_week' => 'integer',
            'day_of_month' => 'integer',
            'overrides_json' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TournamentTemplate::class, 'tournament_template_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Tournament::class, 'schedule_slot_id');
    }
}
