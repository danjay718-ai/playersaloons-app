<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TournamentTeamSearchEntry extends Model
{
    protected $fillable = ['tournament_id', 'user_id', 'platform_id', 'game_id_value', 'ready_mode', 'status', 'matched_at'];

    protected function casts(): array
    {
        return ['matched_at' => 'datetime'];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class)->withTrashed();
    }
}
