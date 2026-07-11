<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    /** @var list<string> */
    protected $fillable = ['uuid', 'referrer_id', 'referred_user_id', 'status', 'referrer_reward', 'referred_reward', 'rewarded_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rewarded_at' => 'datetime', 'referrer_reward' => 'decimal:2', 'referred_reward' => 'decimal:2'];
    }

    /** @return BelongsTo<User, $this> */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
