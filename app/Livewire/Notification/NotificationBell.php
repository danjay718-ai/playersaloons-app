<?php

declare(strict_types=1);

namespace App\Livewire\Notification;

use App\Modules\Community\Models\Notification;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    /** @var Collection<int, Notification> */
    public Collection $notifications;

    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->loadNotifications();
    }

    private function loadNotifications(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->notifications = $user->notifications()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $this->unreadCount = $user->notifications()
            ->whereNull('read_at')
            ->count();
    }

    #[On('notification.received')]
    public function onNotificationReceived(): void
    {
        $this->loadNotifications();
    }

    public function markAsRead(int $id): void
    {
        /** @var User $user */
        $user = auth()->user();

        $user->notifications()->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);

        $this->loadNotifications();
    }

    public function openNotification(int $id): ?RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        if ($notification->action_url !== null && str_starts_with($notification->action_url, '/')) {
            return redirect()->to($notification->action_url);
        }

        $this->loadNotifications();

        return null;
    }

    public function markAllRead(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        $this->loadNotifications();
    }

    public function render(): View
    {
        return view('livewire.notification.notification-bell');
    }
}
