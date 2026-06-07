<?php

namespace App\Modules\Tournament\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentParticipant extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tournament_id',
        'registration_id',
        'user_id',
        'team_id',
        'seed',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seed' => 'integer',
        ];
    }

    /**
     * Get the tournament.
     *
     * @return BelongsTo<Tournament, TournamentParticipant>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the registration details for this participant.
     *
     * @return BelongsTo<TournamentRegistration, TournamentParticipant>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'registration_id');
    }

    /**
     * Get the participant's user.
     *
     * @return BelongsTo<User, TournamentParticipant>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the participant's team, if applicable.
     *
     * @return BelongsTo<Team, TournamentParticipant>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
