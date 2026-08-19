<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TournamentTeamMember extends Model
{
    protected $fillable = ['tournament_id', 'tournament_team_id', 'user_id', 'role', 'game_id_value', 'ready_mode'];

    public function tournamentTeam(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
