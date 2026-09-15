<?php

namespace App\Modules\Match\Models;

use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $tournament_id
 * @property int $round_id
 * @property int|null $player_a_registration_id
 * @property int|null $player_b_registration_id
 * @property int|null $winner_registration_id
 * @property MatchStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $result_submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tournament $tournament
 * @property-read Round $round
 * @property-read TournamentRegistration|null $playerARegistration
 * @property-read TournamentRegistration|null $playerBRegistration
 * @property-read TournamentRegistration|null $winnerRegistration
 */
class GameMatch extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'matches';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'tournament_id',
        'round_id',
        'player_a_registration_id',
        'player_b_registration_id',
        'winner_registration_id',
        'status',
        'active_attempt_number',
        'scheduled_at',
        'ready_started_at',
        'ready_deadline_at',
        'extra_wait_started_at',
        'extra_wait_deadline_at',
        'player_a_ready_at',
        'player_b_ready_at',
        'absence_reported_by_registration_id',
        'lobby_code',
        'lobby_password',
        'server_region',
        'lobby_instructions',
        'double_no_show_at',
        'started_at',
        'completed_at',
        'stalled_deadline_at',
        'round_deadline_at',
        'final_resolution_eligible_at',
        'final_resolution_notified_at',
        'resolution_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'active_attempt_number' => 'integer',
            'scheduled_at' => 'datetime',
            'ready_started_at' => 'datetime',
            'ready_deadline_at' => 'datetime',
            'extra_wait_started_at' => 'datetime',
            'extra_wait_deadline_at' => 'datetime',
            'player_a_ready_at' => 'datetime',
            'player_b_ready_at' => 'datetime',
            'lobby_password' => 'encrypted',
            'double_no_show_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'result_submitted_at' => 'datetime',
            'stalled_deadline_at' => 'datetime',
            'round_deadline_at' => 'datetime',
            'final_resolution_eligible_at' => 'datetime',
            'final_resolution_notified_at' => 'datetime',
        ];
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(MatchAttempt::class, 'match_id');
    }

    public function isTimedOut(): bool
    {
        if ($this->status !== MatchStatus::WAITING_FOR_CONFIRMATION || ! $this->result_submitted_at) {
            return false;
        }

        $waitTime = $this->tournament->waiting_result_time;

        return $this->result_submitted_at->addMinutes($waitTime)->isPast();
    }

    /**
     * Get the tournament.
     *
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class)->withTrashed();
    }

    /**
     * Get the round.
     *
     * @return BelongsTo<Round, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(Round::class);
    }

    /**
     * Get registration details for Player A.
     *
     * @return BelongsTo<TournamentRegistration, $this>
     */
    public function playerARegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'player_a_registration_id');
    }

    /**
     * Get registration details for Player B.
     *
     * @return BelongsTo<TournamentRegistration, $this>
     */
    public function playerBRegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'player_b_registration_id');
    }

    /**
     * Get registration details for the winner.
     *
     * @return BelongsTo<TournamentRegistration, $this>
     */
    public function winnerRegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'winner_registration_id');
    }

    /**
     * Get result submissions for the match.
     *
     * @return HasMany<MatchResultSubmission, $this>
     */
    public function resultSubmissions(): HasMany
    {
        return $this->hasMany(MatchResultSubmission::class, 'match_id');
    }

    /**
     * Get disputes associated with the match.
     *
     * @return HasMany<MatchDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(MatchDispute::class, 'match_id');
    }

    public function rematchVotes(): HasMany
    {
        return $this->hasMany(MatchRematchVote::class, 'match_id');
    }
}
