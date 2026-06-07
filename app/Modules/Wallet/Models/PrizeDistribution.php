<?php

namespace App\Modules\Wallet\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $wallet_id
 * @property int $tournament_id
 * @property int $rank
 * @property float|string $amount
 * @property string $distribution_reference_uuid
 * @property string $status
 * @property Carbon|null $created_at
 * @property-read Wallet $wallet
 * @property-read Tournament $tournament
 */
class PrizeDistribution extends Model
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
        'tournament_id',
        'rank',
        'amount',
        'distribution_reference_uuid',
        'status',
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
            'rank' => 'integer',
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
     * Get the wallet this prize was paid to.
     *
     * @return BelongsTo<Wallet, PrizeDistribution>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the user who received the prize.
     *
     * @return BelongsTo<User, PrizeDistribution>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tournament.
     *
     * @return BelongsTo<Tournament, PrizeDistribution>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
