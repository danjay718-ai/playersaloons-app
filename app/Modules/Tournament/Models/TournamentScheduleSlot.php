<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_template_id
 * @property string|null $label
 * @property string $local_start_time
 * @property Carbon|null $schedule_start_at
 * @property Carbon|null $schedule_end_at
 * @property int|null $day_of_week
 * @property int|null $day_of_month
 * @property array<string, mixed>|null $overrides_json
 * @property bool $is_active
 * @property int $sort_order
 * @property-read TournamentTemplate|null $template
 * @property-read Collection<int, Tournament> $occurrences
 */
final class TournamentScheduleSlot extends Model
{
    use SoftDeletes;

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

    /** @return BelongsTo<TournamentTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TournamentTemplate::class, 'tournament_template_id')->withTrashed();
    }

    /** @return HasMany<Tournament, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(Tournament::class, 'schedule_slot_id');
    }
}
