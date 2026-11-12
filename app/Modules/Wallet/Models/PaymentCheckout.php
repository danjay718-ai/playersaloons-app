<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentCheckout extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return BelongsTo<GatewayConfiguration, $this> */
    public function gatewayConfiguration(): BelongsTo
    {
        return $this->belongsTo(GatewayConfiguration::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'fee_amount' => 'decimal:2', 'total' => 'decimal:2', 'completed_at' => 'datetime'];
    }
}
