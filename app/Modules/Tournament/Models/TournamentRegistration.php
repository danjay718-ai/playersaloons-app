<?php

namespace App\Modules\Tournament\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $tournament_id
 * @property int $user_id
 * @property int|null $team_id
 * @property RegistrationStatus $status
 * @property PaymentStatus $payment_status
 * @property Carbon|null $registered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tournament $tournament
 * @property-read User|null $user
 * @property-read Team|null $team
 */
class TournamentRegistration extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
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
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the registered user.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the registered team, if applicable.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function rosterMembers(): HasMany
    {
        return $this->hasMany(TournamentRegistrationMember::class, 'registration_id');
    }

    public function includesUser(int $userId): bool
    {
        return (int) $this->user_id === $userId
            || $this->rosterMembers()->where('user_id', $userId)->exists();
    }
}
