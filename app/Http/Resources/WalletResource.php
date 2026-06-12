<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\WalletStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 *
 * @property-read string $uuid
 * @property-read string $cached_balance
 * @property-read WalletStatus $status
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
