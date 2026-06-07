<?php

namespace App\Modules\Wallet\Models;

use App\Shared\Enums\LedgerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntry extends Model
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
        'reference_type',
        'reference_id',
        'type',
        'amount',
        'running_balance',
        'description',
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
            'type' => LedgerType::class,
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
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
     * Get the wallet this entry belongs to.
     *
     * @return BelongsTo<Wallet, LedgerEntry>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
