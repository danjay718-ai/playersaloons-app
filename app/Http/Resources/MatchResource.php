<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Match\Models\GameMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameMatch
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
            'tournament_uuid' => $this->whenLoaded('tournament', fn () => $this->tournament->uuid),
            'round_number' => $this->whenLoaded('round', fn () => $this->round->round_number),
            'status' => $this->status->value ?? $this->status,
            'scheduled_at' => $this->scheduled_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'result_deadline_at' => $this->when(
                (int) ($this->tournament?->workflow_version ?? 1) === 2,
                fn () => $this->attempts()->where('attempt_number', $this->active_attempt_number)->value('result_deadline_at'),
            ),
            'active_attempt_number' => (int) ($this->active_attempt_number ?? 1),
            'player_a' => $this->whenLoaded('playerARegistration', fn () => $this->playerARegistration?->user ? [
                'uuid' => $this->playerARegistration->user->uuid,
                'username' => $this->playerARegistration->user->username,
            ] : null),
            'player_b' => $this->whenLoaded('playerBRegistration', fn () => $this->playerBRegistration?->user ? [
                'uuid' => $this->playerBRegistration->user->uuid,
                'username' => $this->playerBRegistration->user->username,
            ] : null),
            'winner' => $this->whenLoaded('winnerRegistration', fn () => $this->winnerRegistration?->user ? [
                'uuid' => $this->winnerRegistration->user->uuid,
                'username' => $this->winnerRegistration->user->username,
            ] : null),
        ];
    }
}
