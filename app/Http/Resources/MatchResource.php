<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\Match\Models\GameMatch
 */
class MatchResource extends JsonResource
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
            'tournament_uuid' => $this->tournament->uuid,
            'round_number' => $this->round->round_number,
            'status' => $this->status->value ?? $this->status,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'player_a' => $this->playerARegistration?->user ? [
                'uuid' => $this->playerARegistration->user->uuid,
                'username' => $this->playerARegistration->user->username,
            ] : null,
            'player_b' => $this->playerBRegistration?->user ? [
                'uuid' => $this->playerBRegistration->user->uuid,
                'username' => $this->playerBRegistration->user->username,
            ] : null,
            'winner' => $this->winnerRegistration?->user ? [
                'uuid' => $this->winnerRegistration->user->uuid,
                'username' => $this->winnerRegistration->user->username,
            ] : null,
        ];
    }
}
