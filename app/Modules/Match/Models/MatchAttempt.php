<?php

declare(strict_types=1);

namespace App\Modules\Match\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MatchAttempt extends Model
{
    protected $fillable = [
        'uuid', 'match_id', 'attempt_number', 'status', 'first_submitted_at',
        'result_deadline_at', 'stalled_deadline_at', 'resolved_at', 'resolution',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'first_submitted_at' => 'datetime',
            'result_deadline_at' => 'datetime',
            'stalled_deadline_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(MatchResultSubmission::class);
    }
}
