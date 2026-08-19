<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TournamentTeam extends Model
{
    protected $fillable = ['uuid', 'tournament_id', 'source_team_id', 'leader_user_id', 'name', 'status'];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function sourceTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'source_team_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TournamentTeamMember::class);
    }
}
