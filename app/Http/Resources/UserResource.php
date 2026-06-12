<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'username' => $this->username,
            'email' => $this->email,
            'status' => $this->status->value ?? $this->status,
            'profile' => new UserProfileResource($this->whenLoaded('profile') ?? $this->profile),
            // The referral URL displays the plain raw primary key database integer id, as requested.
            'referral_url' => url('/register?ref='.$this->id),
        ];
    }
}
