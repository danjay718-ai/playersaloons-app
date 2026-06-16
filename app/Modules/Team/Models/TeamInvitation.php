<?php

declare(strict_types=1);

namespace App\Modules\Team\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\TeamInvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $team_id
 * @property int $invited_user_id
 * @property int $invited_by_user_id
 * @property TeamInvitationStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User $invitee
 * @property-read User $inviter
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static static create(array<string, mixed> $attributes = [])
 */
class TeamInvitation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'team_id',
        'invited_user_id',
        'invited_by_user_id',
        'status',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TeamInvitationStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the invited user.
     *
     * @return BelongsTo<User, $this>
     */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    /**
     * Get the user who sent the invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
