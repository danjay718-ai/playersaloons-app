<?php

namespace App\Modules\Match\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $match_id
 * @property int $submitted_by
 * @property int $winner_registration_id
 * @property string|null $notes
 * @property Carbon|null $submitted_at
 * @property-read GameMatch $match
 * @property-read User $submittedBy
 * @property-read TournamentRegistration $winnerRegistration
 */
class MatchResultSubmission extends Model
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
        'match_id',
        'submitted_by',
        'winner_registration_id',
        'notes',
        'submitted_at',
        'proof_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * Boot the model and register immutable guards.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Cannot update immutable record.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Cannot delete immutable record.');
        });
    }

    /**
     * Get the match.
     *
     * @return BelongsTo<GameMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class);
    }

    /**
     * Alias for submittedBy relationship.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->submittedBy();
    }

    /**
     * Get the user who submitted the result.
     *
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get registration details for the winner selected in this submission.
     *
     * @return BelongsTo<TournamentRegistration, $this>
     */
    public function winnerRegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'winner_registration_id');
    }
}
