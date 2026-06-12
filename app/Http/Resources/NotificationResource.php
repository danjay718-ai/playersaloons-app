<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Modules\Community\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Notification
 *
 * @property-read string $uuid
 * @property-read string $type
 * @property-read string $title
 * @property-read string $message
 * @property-read Carbon|null $read_at
 * @property-read Carbon $created_at
 */
class NotificationResource extends JsonResource
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
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
