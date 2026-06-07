<?php

namespace App\Modules\Team\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\TeamInvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
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
     * @return BelongsTo<Team, TeamInvitation>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the invited user.
     *
     * @return BelongsTo<User, TeamInvitation>
     */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    /**
     * Get the user who sent the invitation.
     *
     * @return BelongsTo<User, TeamInvitation>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
