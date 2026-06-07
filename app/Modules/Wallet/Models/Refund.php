<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'user_id',
        'registration_id',
        'amount',
        'refund_reason',
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
     * Get the user who received the refund.
     *
     * @return BelongsTo<User, Refund>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tournament registration details for this refund.
     *
     * @return BelongsTo<TournamentRegistration, Refund>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'registration_id');
    }
}
