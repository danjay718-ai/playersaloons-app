<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Team\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Team
 */
class TeamResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'logo_url' => $this->logo_path,
            'captain' => $this->whenLoaded('captain', fn () => [
                'uuid' => $this->captain->uuid,
                'username' => $this->captain->username,
            ]),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($member) => [
                'uuid' => $member->relationLoaded('user') ? $member->user->uuid : null,
                'username' => $member->relationLoaded('user') ? $member->user->username : null,
                'role' => $member->role,
                'status' => $member->status,
            ])),
        ];
    }
}
