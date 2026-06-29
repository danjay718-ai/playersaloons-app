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
            'min_participants' => $this->min_participants,
            'registration_open_at' => $this->registration_open_at,
            'registration_close_at' => $this->registration_close_at,
            'checkin_open_at' => $this->checkin_open_at,
            'checkin_close_at' => $this->checkin_close_at,
            'start_at' => $this->start_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'game' => [
                'uuid' => $this->game->uuid,
                'name' => $this->game->translations()->where('locale', app()->getLocale())->first()?->name
                    ?? $this->game->translations()->where('locale', 'en')->first()?->name
                    ?? $this->game->slug,
            ],
        ];
    }
}
