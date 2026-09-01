<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Tournament\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tournament
 */
class TournamentResource extends JsonResource
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
            'status' => $this->status->value ?? $this->status,
            'entry_fee' => $this->entry_fee,
            'prize_pool' => $this->prize_pool,
            'max_participants' => $this->max_participants,
            'max_teams' => $this->max_participants,
            'workflow_version' => (int) ($this->workflow_version ?? 1),
            'join_closes_at' => $this->join_closes_at?->toIso8601String(),
            'min_participants' => $this->min_participants,
            'registration_open_at' => $this->registration_open_at,
            'registration_close_at' => $this->registration_close_at,
            'checkin_open_at' => $this->checkin_open_at,
            'checkin_close_at' => $this->checkin_close_at,
            'start_at' => $this->start_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'game' => $this->whenLoaded('game', function () {
                $translations = $this->game->relationLoaded('translations')
                    ? $this->game->translations
                    : $this->game->translations()->get();
                $locale = app()->getLocale();

                return [
                    'uuid' => $this->game->uuid,
                    'name' => $translations->firstWhere('locale', $locale)?->name
                        ?? $translations->firstWhere('locale', 'en')?->name
                        ?? $this->game->slug,
                ];
            }),
        ];
    }
}
