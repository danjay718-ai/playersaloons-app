<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Identity\Models\User;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property WithdrawalStatus $status
 * @property string $amount
 * @property array{iban: string, name: string, email: string}|null $payout_recipient
 */
class Withdrawal extends Model
{
    protected $hidden = ['payout_recipient'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'wallet_id',
        'user_id',
        'status',
        'amount',
        'reviewed_by',
        'review_notes',
        'reviewed_at',
        'processed_at',
        'payout_provider',
        'payout_currency',
        'payout_recipient',
        'payout_reference',
        'payout_status',
        'payout_submitted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
            'amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
            'processed_at' => 'datetime',
            'payout_recipient' => 'encrypted:array',
            'payout_submitted_at' => 'datetime',
        ];
    }

    /**
     * Get the wallet this withdrawal was requested from.
     *
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the user who requested the withdrawal.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the admin/reviewer who reviewed the withdrawal.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
