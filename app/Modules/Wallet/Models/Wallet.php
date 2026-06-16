<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\WalletStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property WalletStatus $status
 */
class Wallet extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'cached_balance',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cached_balance' => 'decimal:2',
            'status' => WalletStatus::class,
        ];
    }

    /**
     * Get the user who owns this wallet.
     *
     * @return BelongsTo<User, Wallet>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get ledger entries associated with this wallet.
     *
     * @return HasMany<LedgerEntry, Wallet>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Get transactions associated with this wallet.
     *
     * @return HasMany<WalletTransaction, Wallet>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Get deposits associated with this wallet.
     *
     * @return HasMany<Deposit, Wallet>
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    /**
     * Get withdrawals associated with this wallet.
     *
     * @return HasMany<Withdrawal, Wallet>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * Get refunds associated with this wallet.
     *
     * @return HasMany<Refund, Wallet>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * Get prize distributions associated with this wallet.
     *
     * @return HasMany<PrizeDistribution, Wallet>
     */
    public function prizeDistributions(): HasMany
    {
        return $this->hasMany(PrizeDistribution::class);
    }
}
