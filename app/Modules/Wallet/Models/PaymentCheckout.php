<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCheckout extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'fee_amount' => 'decimal:2', 'total' => 'decimal:2', 'completed_at' => 'datetime'];
    }
}
