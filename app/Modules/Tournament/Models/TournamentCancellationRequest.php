<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TournamentCancellationRequest extends Model
{
    protected $fillable = [
        'uuid', 'tournament_id', 'registration_id', 'requested_by', 'status',
        'eligible_voter_count', 'eligible_voter_ids', 'required_approvals', 'requested_at', 'expires_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'eligible_voter_count' => 'integer',
            'eligible_voter_ids' => 'array',
            'required_approvals' => 'integer',
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(TournamentCancellationVote::class, 'request_id');
    }
}
