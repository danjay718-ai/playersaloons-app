<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Wallet\Models\Withdrawal
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
            'amount' => $this->amount,
            'status' => $this->status->value ?? $this->status,
            'created_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
            'processed_at' => $this->processed_at,
        ];
    }
}
