<?php

namespace App\Modules\Match\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $uuid
 * @property int $dispute_id
 * @property int $uploaded_by
 * @property string $file_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read \App\Modules\Match\Models\MatchDispute $dispute
 * @property-read \App\Modules\Identity\Models\User $uploadedBy
 */
class MatchEvidence extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'dispute_id',
        'uploaded_by',
        'file_path',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model and register immutable guards.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Cannot update immutable record.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Cannot delete immutable record.');
        });
    }

    /**
     * Get the dispute.
     *
     * @return BelongsTo<MatchDispute, $this>
     */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(MatchDispute::class, 'dispute_id');
    }

    /**
     * Get the user who uploaded this evidence.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
