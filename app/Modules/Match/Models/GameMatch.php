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
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'tournament_id',
        'round_id',
        'player_a_registration_id',
        'player_b_registration_id',
        'winner_registration_id',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
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
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
}
