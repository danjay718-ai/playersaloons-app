<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Mail\SystemNotificationMail;
use App\Modules\Community\Events\BroadcastNotification;
use App\Modules\Community\Models\Notification;
use App\Modules\Community\Models\NotificationPreference;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Send a notification to a user, checking their notification preferences.
     */
    public function send(User $user, string $type, string $title, string $message, ?string $actionUrl = null): ?Notification
    {
        // Action links are deliberately limited to local paths so a malformed
        // event can never turn a trusted notification into an external link.
        $actionUrl = $actionUrl !== null && str_starts_with($actionUrl, '/') ? $actionUrl : null;
        /** @var NotificationPreference|null $loadedPreferences */
        $loadedPreferences = $user->relationLoaded('notificationPreference')
            ? $user->getRelation('notificationPreference')
            : null;
        /** @var NotificationPreference $preferences */
        $preferences = $loadedPreferences ?? NotificationPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'email_enabled' => true,
                'in_app_enabled' => true,
                'realtime_enabled' => true,
            ]
        );
        $user->setRelation('notificationPreference', $preferences);

        $notification = null;

        if ($preferences->in_app_enabled) {
            /** @var Notification $notification */
            $notification = Notification::query()->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'read_at' => null,
            ]);
        }

        if ($preferences->realtime_enabled) {
            broadcast(new BroadcastNotification($user->uuid, [
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
            ]));
        }

        if ($preferences->email_enabled) {
            Mail::to($user->email)->queue(new SystemNotificationMail($title, $message, $actionUrl));
        }

        return $notification;
    }
}
