<?php

namespace App\Modules\Tournament\Models;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Support\CompetitionPlatforms;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Database\Eloquent\Builder;
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
 * @property CompetitionType $competition_type
 * @property string $name
 * @property string $slug
 * @property TournamentStatus $status
 * @property float|string $entry_fee
 * @property float|string $prize_pool
 * @property float|string|null $advertised_prize_pool
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
 * @property-read Collection<int, StreamChannel> $streamChannels
 * @property-read Collection|TournamentAnnouncement[] $announcements
 * @property-read User $creator
 */
class Tournament extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    protected static function booted(): void
    {
        static::updating(function (Tournament $tournament): void {
            if ((int) $tournament->workflow_version !== 2) {
                return;
            }

            $presentation = ['banner_url', 'description', 'rules'];
            $structural = [
                'name', 'game_id', 'platform_id', 'platform_ids', 'competition_type', 'entry_fee', 'max_participants',
                'min_participants', 'team_size', 'registration_open_at', 'registration_close_at',
                'start_at', 'end_at', 'join_closes_at', 'timezone', 'frequency', 'waiting_result_time',
                'round_duration_seconds', 'winning_points', 'winner_bonus_xp', 'full_first_bps',
                'full_second_bps', 'full_platform_bps', 'underfilled_first_bps', 'underfilled_platform_bps',
            ];
            $dirtyConfiguration = array_intersect(array_keys($tournament->getDirty()), [...$presentation, ...$structural]);
            if ($dirtyConfiguration === []) {
                return;
            }

            $originalStart = $tournament->getRawOriginal('start_at');
            if ($originalStart !== null && Carbon::parse($originalStart)->lessThanOrEqualTo(now())) {
                throw new \LogicException('Tournament V2 configuration is locked after its Start Date and Time: '.implode(', ', $dirtyConfiguration).'.');
            }

            if (array_intersect($dirtyConfiguration, $structural) !== []
                && $tournament->registrations()->exists()) {
                throw new \LogicException('Only banner, description, and rules may be edited after the first player joins: '.implode(', ', array_intersect($dirtyConfiguration, $structural)).'.');
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'workflow_version',
        'template_id',
        'schedule_slot_id',
        'occurrence_period_key',
        'game_id',
        'competition_type',
        'name',
        'slug',
        'status',
        'entry_fee',
        'prize_pool',
        'advertised_prize_pool',
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
        'timezone',
        'banner_url',
        'is_featured',
        'description',
        'rules',
        'platform_id',
        'platform_ids',
        'waiting_time',
        'match_ready_minutes',
        'match_extra_wait_minutes',
        'waiting_result_time',
        'team_size',
        'prize_1st',
        'prize_2nd',
        'prize_3rd',
        'winning_points',
        'play_xp',
        'winner_bonus_xp',
        'end_at',
        'join_closes_at',
        'round_duration_seconds',
        'full_first_bps',
        'full_second_bps',
        'full_platform_bps',
        'underfilled_first_bps',
        'underfilled_platform_bps',
        'financial_calculation_version',
        'finalized_joined_entries',
        'finalized_gross_pool',
        'finalized_commission_amount',
        'finalized_first_prize',
        'finalized_second_prize',
        'financial_finalized_at',
        'payout_status',
        'completion_reason',
        'registration_duration_minutes',
        'extra_registration_minutes',
        'extra_registration_started_at',
        'registration_locked_at',
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
            'platform_ids' => 'array',
            'status' => TournamentStatus::class,
            'workflow_version' => 'integer',
            'competition_type' => CompetitionType::class,
            'entry_fee' => 'decimal:2',
            'prize_pool' => 'decimal:2',
            'advertised_prize_pool' => 'decimal:2',
            'max_participants' => 'integer',
            'min_participants' => 'integer',
            'registration_open_at' => 'datetime',
            'registration_close_at' => 'datetime',
            'checkin_open_at' => 'datetime',
            'checkin_close_at' => 'datetime',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'join_closes_at' => 'datetime',
            'round_duration_seconds' => 'integer',
            'full_first_bps' => 'integer',
            'full_second_bps' => 'integer',
            'full_platform_bps' => 'integer',
            'underfilled_first_bps' => 'integer',
            'underfilled_platform_bps' => 'integer',
            'financial_calculation_version' => 'integer',
            'finalized_joined_entries' => 'integer',
            'finalized_gross_pool' => 'decimal:2',
            'finalized_commission_amount' => 'decimal:2',
            'finalized_first_prize' => 'decimal:2',
            'finalized_second_prize' => 'decimal:2',
            'financial_finalized_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'prize_1st' => 'decimal:2',
            'prize_2nd' => 'decimal:2',
            'prize_3rd' => 'decimal:2',
            'waiting_time' => 'integer',
            'match_ready_minutes' => 'integer',
            'match_extra_wait_minutes' => 'integer',
            'waiting_result_time' => 'integer',
            'team_size' => 'integer',
            'winning_points' => 'integer',
            'play_xp' => 'integer',
            'winner_bonus_xp' => 'integer',
            'registration_duration_minutes' => 'integer',
            'extra_registration_minutes' => 'integer',
            'extra_registration_started_at' => 'datetime',
            'registration_locked_at' => 'datetime',
            'is_auto_cancel_underfilled' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Get the template this tournament was created from.
     *
     * @return BelongsTo<TournamentTemplate, Tournament>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TournamentTemplate::class, 'template_id')->withTrashed();
    }

    public function scheduleSlot(): BelongsTo
    {
        return $this->belongsTo(TournamentScheduleSlot::class, 'schedule_slot_id')->withTrashed();
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(TournamentCancellationRequest::class);
    }

    /**
     * Get the game this tournament is played on.
     *
     * @return BelongsTo<Game, Tournament>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class)->withTrashed();
    }

    /**
     * Get the platform for the tournament.
     *
     * @return BelongsTo<Platform, Tournament>
     */
    /** @return array<int, int> */
    public function supportedPlatformIds(): array
    {
        return CompetitionPlatforms::ids([
            'platform_ids' => $this->platform_ids,
            'platform_id' => $this->platform_id,
        ]);
    }

    public function getPlatformNamesAttribute(): string
    {
        return Platform::withTrashed()->whereIn('id', $this->supportedPlatformIds())->orderBy('name')->pluck('name')->implode(', ');
    }

    public function scopeForPlatform(Builder $query, int $platformId): void
    {
        $query->where(fn ($query) => $query->whereJsonContains('platform_ids', $platformId)
            ->orWhere(fn ($legacy) => $legacy->whereNull('platform_ids')->where('platform_id', $platformId)));
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class)->withTrashed();
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
     * Get stream channels attached to this tournament.
     *
     * @return HasMany<StreamChannel, Tournament>
     */
    public function streamChannels(): HasMany
    {
        return $this->hasMany(StreamChannel::class);
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
     * Get checkins for the tournament via registrations.
     *
     * @return HasManyThrough<TournamentCheckin, TournamentRegistration, Tournament>
     */
    public function checkins(): HasManyThrough
    {
        return $this->hasManyThrough(TournamentCheckin::class, TournamentRegistration::class, 'tournament_id', 'registration_id');
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
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
