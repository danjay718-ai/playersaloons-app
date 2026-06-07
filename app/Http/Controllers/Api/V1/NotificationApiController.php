<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    /**
     * Get the authenticated user's notifications.
     */
    public function index(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', '15');
        $notifications = $request->user()->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return NotificationResource::collection($notifications);
    }

    /**
     * Mark a notification as read.
     */
    public function read(string $uuid, Request $request): JsonResponse
    {
        $notification = $request->user()->notifications()
            ->where('uuid', $uuid)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Notification marked as read.',
            'notification' => new NotificationResource($notification),
        ], 200);
    }
}
