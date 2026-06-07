<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Wallet\Models\LedgerEntry
 * @property-read string $uuid
 * @property-read \App\Shared\Enums\LedgerType $type
 * @property-read string $amount
 * @property-read string $running_balance
 * @property-read string $description
 * @property-read \Illuminate\Support\Carbon $created_at
 */
class LedgerEntryResource extends JsonResource
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
            'type' => $this->type->value ?? $this->type,
            'amount' => $this->amount,
            'running_balance' => $this->running_balance,
            'description' => $this->description,
            'created_at' => $this->created_at,
        ];
    }
}
