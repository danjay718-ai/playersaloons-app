<?php

namespace App\Modules\Tournament\Models;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $template_id
 * @property int $game_id
 * @property string $name
 * @property string $slug
 * @property TournamentStatus $status
 * @property float|string $entry_fee
 * @property float|string $prize_pool
 * @property int $max_participants
 * @property int $min_participants
 * @property Carbon|null $registration_open_at
 * @property Carbon|null $registration_close_at
 * @property Carbon|null $checkin_open_at
 * @property Carbon|null $checkin_close_at
 * @property Carbon|null $start_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read TournamentTemplate|null $template
 * @property-read Game $game
 * @property-read Collection|TournamentRegistration[] $registrations
 * @property-read Collection|TournamentParticipant[] $participants
 * @property-read Collection|Bracket[] $brackets
 * @property-read TournamentCancellation|null $cancellation
 * @property-read Collection|TournamentRule[] $rules
 * @property-read Collection|TournamentAnnouncement[] $announcements
 * @property-read User $creator
 */
class Tournament extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'template_id',
        'game_id',
        'name',
        'slug',
        'status',
        'entry_fee',
        'prize_pool',
        'max_participants',
        'min_participants',
        'registration_open_at',
        'registration_close_at',
        'checkin_open_at',
        'checkin_close_at',
        'start_at',
        'completed_at',
        'cancelled_at',
        'created_by',
        'frequency',
        'banner_url',
        'description',
        'rules',
        'platform_id',
        'waiting_time',
        'waiting_result_time',
        'team_size',
        'prize_1st',
        'prize_2nd',
        'prize_3rd',
        'winning_points',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TournamentStatus::class,
            'entry_fee' => 'decimal:2',
            'prize_pool' => 'decimal:2',
            'max_participants' => 'integer',
            'min_participants' => 'integer',
            'registration_open_at' => 'datetime',
            'registration_close_at' => 'datetime',
            'checkin_open_at' => 'datetime',
            'checkin_close_at' => 'datetime',
            'start_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'prize_1st' => 'decimal:2',
            'prize_2nd' => 'decimal:2',
            'prize_3rd' => 'decimal:2',
            'waiting_time' => 'integer',
            'waiting_result_time' => 'integer',
            'team_size' => 'integer',
            'winning_points' => 'integer',
        ];
    }

    /**
     * Get the template this tournament was created from.
     *
     * @return BelongsTo<TournamentTemplate, Tournament>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TournamentTemplate::class, 'template_id');
    }

    /**
     * Get the game this tournament is played on.
     *
     * @return BelongsTo<Game, Tournament>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get the platform for the tournament.
     *
     * @return BelongsTo<Platform, Tournament>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Get registrations for the tournament.
     *
     * @return HasMany<TournamentRegistration, Tournament>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    /**
     * Get participants in the tournament.
     *
     * @return HasMany<TournamentParticipant, Tournament>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    /**
     * Get brackets generated for the tournament.
     *
     * @return HasMany<Bracket, Tournament>
     */
    public function brackets(): HasMany
    {
        return $this->hasMany(Bracket::class);
    }

    /**
     * Get rounds for the tournament (via brackets).
     */
    public function rounds(): HasManyThrough
    {
        return $this->hasManyThrough(Round::class, Bracket::class);
    }

    /**
     * Get the cancellation details of the tournament.
     *
     * @return HasOne<TournamentCancellation, Tournament>
     */
    public function cancellation(): HasOne
    {
        return $this->hasOne(TournamentCancellation::class);
    }

    /**
     * Get rules for the tournament.
     *
     * @return HasMany<TournamentRule, Tournament>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(TournamentRule::class);
    }

    /**
     * Get announcements for the tournament.
     *
     * @return HasMany<TournamentAnnouncement, Tournament>
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(TournamentAnnouncement::class);
    }

    /**
     * Get the user who created the tournament.
     *
     * @return BelongsTo<User, Tournament>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
