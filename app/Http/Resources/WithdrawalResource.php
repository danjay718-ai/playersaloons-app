<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Support\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Withdrawal
 */
class WithdrawalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'currency' => $this->payout_currency ?? Currency::code(),
            'payout_provider' => $this->payout_provider ?? 'manual',
            'payout_status' => $this->payout_status,
            'payout_reference' => $this->payout_reference,
            'amount' => $this->amount,
            'status' => $this->status->value ?? $this->status,
            'created_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
            'processed_at' => $this->processed_at,
        ];
    }
}
