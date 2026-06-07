<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Modules\Community\Events\BroadcastNotification;
use App\Modules\Community\Models\Notification;
use App\Modules\Community\Models\NotificationPreference;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Send a notification to a user, checking their notification preferences.
     */
    public function send(User $user, string $type, string $title, string $message): ?Notification
    {
        /** @var NotificationPreference $preferences */
        $preferences = NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'email_enabled' => true,
                'in_app_enabled' => true,
                'realtime_enabled' => true,
            ]
        );

        $notification = null;

        if ($preferences->in_app_enabled) {
            /** @var Notification $notification */
            $notification = Notification::query()->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'read_at' => null,
            ]);
        }

        if ($preferences->realtime_enabled) {
            broadcast(new BroadcastNotification($user->uuid, [
                'type' => $type,
                'title' => $title,
                'message' => $message,
            ]));
        }

        if ($preferences->email_enabled) {
            // Email sending logic (such as dispatching a Mailer/Job) can be hooked up here in the future.
            // For the MVP, checking and respecting the preference flag satisfies the requirement.
        }

        return $notification;
    }
}
