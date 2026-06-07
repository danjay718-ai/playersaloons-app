<?php

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $wallet_id
 * @property int $tournament_id
 * @property float|string $amount
 * @property string $status
 * @property string $refund_reference_uuid
 * @property Carbon|null $created_at
 * @property-read Wallet $wallet
 * @property-read \App\Modules\Tournament\Models\Tournament $tournament
 */
class Refund extends Model
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
        'wallet_id',
        'tournament_id',
        'amount',
        'status',
        'refund_reference_uuid',
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
            'amount' => 'decimal:2',
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
     * Get the wallet this refund was credited to.
     *
     * @return BelongsTo<Wallet, Refund>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the tournament.
     *
     * @return BelongsTo<Tournament, Refund>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
