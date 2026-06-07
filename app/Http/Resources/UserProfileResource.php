<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Identity\Models\UserProfile
 * @property-read string|null $uuid
 * @property-read string|null $display_name
 * @property-read string|null $avatar_url
 * @property-read string|null $country_code
 * @property-read string|null $timezone
 * @property-read string|null $bio
 */
class UserProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'uuid' => $this->uuid,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'country_code' => $this->country_code,
            'timezone' => $this->timezone,
            'bio' => $this->bio,
        ];
    }
}
