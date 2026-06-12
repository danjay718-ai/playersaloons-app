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
            'captain' => $this->captain ? [
                'uuid' => $this->captain->uuid,
                'username' => $this->captain->username,
            ] : null,
            'members' => $this->members->map(fn ($member) => [
                'uuid' => $member->user->uuid,
                'username' => $member->user->username,
                'role' => $member->role,
                'status' => $member->status,
            ]),
        ];
    }
}
