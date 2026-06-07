<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Wallet\Models\Wallet
 * @property-read string $uuid
 * @property-read string $cached_balance
 * @property-read \App\Shared\Enums\WalletStatus $status
 */
class WalletResource extends JsonResource
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
            'balance' => $this->cached_balance,
            'status' => $this->status,
        ];
    }
}
