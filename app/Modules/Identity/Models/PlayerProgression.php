<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerProgression extends Model
{
    public const XP_PER_LEVEL = 500;

    protected $fillable = ['user_id', 'experience_points', 'level', 'tournaments_completed'];

    protected function casts(): array
    {
        return [
            'experience_points' => 'integer',
            'level' => 'integer',
            'tournaments_completed' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function xpWithinLevel(): int
    {
        return $this->experience_points % self::XP_PER_LEVEL;
    }

    public function progressPercent(): int
    {
        return (int) floor(($this->xpWithinLevel() / self::XP_PER_LEVEL) * 100);
    }
}
