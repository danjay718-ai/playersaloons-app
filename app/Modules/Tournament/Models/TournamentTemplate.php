<?php

namespace App\Modules\Tournament\Models;

use App\Modules\CMS\Models\Game;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\RecurrenceFrequency;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $game_id
 * @property CompetitionType $competition_type
 * @property string $name
 * @property string $format
 * @property int $max_participants
 * @property int $min_participants
 * @property float|string $entry_fee
 * @property string $prize_model
 * @property int $checkin_minutes
 * @property bool $is_recurring
 * @property RecurrenceFrequency|null $recurrence_frequency
 * @property string $timezone
 * @property Carbon|null $next_run_at
 * @property Carbon|null $last_generated_at
 * @property int $generation_lead_minutes
 * @property array|null $settings_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection|TournamentTemplatePrize[] $prizes
 */
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
        'competition_type',
        'name',
        'format',
        'max_participants',
        'min_participants',
        'entry_fee',
        'prize_model',
        'checkin_minutes',
        'is_recurring',
        'recurrence_frequency',
        'timezone',
        'next_run_at',
        'last_generated_at',
        'generation_lead_minutes',
        'settings_json',
        'is_auto_cancel_underfilled',
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
            'competition_type' => CompetitionType::class,
            'recurrence_frequency' => RecurrenceFrequency::class,
            'next_run_at' => 'datetime',
            'last_generated_at' => 'datetime',
            'generation_lead_minutes' => 'integer',
            'settings_json' => 'array',
            'is_auto_cancel_underfilled' => 'boolean',
        ];
    }

    /**
     * Get the game this template belongs to.
     *
     * @return BelongsTo<Game, TournamentTemplate>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class)->withTrashed();
    }

    /**
     * Get prizes configured for this template.
     *
     * @return HasMany<TournamentTemplatePrize, TournamentTemplate>
     */
    public function prizes(): HasMany
    {
        return $this->hasMany(TournamentTemplatePrize::class, 'template_id');
    }
}
