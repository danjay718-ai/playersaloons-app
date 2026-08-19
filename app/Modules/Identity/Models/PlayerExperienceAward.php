<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerExperienceAward extends Model
{
    protected $fillable = ['uuid', 'user_id', 'source_type', 'source_id', 'reason', 'amount', 'metadata'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
