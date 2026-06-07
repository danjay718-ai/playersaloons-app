<?php

namespace App\Modules\Team\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Team extends Model implements HasMedia
{
    use InteractsWithMedia, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'logo_path',
        'captain_user_id',
        'status',
    ];

    /**
     * Get the team's members.
     *
     * @return HasMany<TeamMember, Team>
     */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * Get the team's invitations.
     *
     * @return HasMany<TeamInvitation, Team>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    /**
     * Get the captain of the team.
     *
     * @return BelongsTo<User, Team>
     */
    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_user_id');
    }
}
