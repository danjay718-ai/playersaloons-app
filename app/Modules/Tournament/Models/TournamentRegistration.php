<?php

namespace App\Modules\Tournament\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentRegistration extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'tournament_id',
        'user_id',
        'team_id',
        'status',
        'payment_status',
        'registered_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'payment_status' => PaymentStatus::class,
            'registered_at' => 'datetime',
        ];
    }

    /**
     * Get the tournament.
     *
     * @return BelongsTo<Tournament, TournamentRegistration>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the registered user.
     *
     * @return BelongsTo<User, TournamentRegistration>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the registered team, if applicable.
     *
     * @return BelongsTo<Team, TournamentRegistration>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
