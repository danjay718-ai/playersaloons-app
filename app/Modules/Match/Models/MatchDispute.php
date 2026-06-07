<?php

namespace App\Modules\Match\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MatchDispute extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'match_id',
        'opened_by',
        'status',
        'resolution',
        'resolved_by',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'resolution' => DisputeResolution::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the match.
     *
     * @return BelongsTo<GameMatch, $this>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class);
    }

    /**
     * Get the user who opened the dispute.
     *
     * @return BelongsTo<User, MatchDispute>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * Get the user who resolved the dispute.
     *
     * @return BelongsTo<User, MatchDispute>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get evidence uploads associated with this dispute.
     *
     * @return HasMany<MatchEvidence, MatchDispute>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(MatchEvidence::class, 'dispute_id');
    }
}
