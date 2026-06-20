<?php

declare(strict_types=1);

namespace App\Livewire\Notification;

use App\Modules\Community\Models\Notification;
use Illuminate\Database\Eloquent\Collection;
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
        /** @var \App\Modules\Identity\Models\User $user */
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
        /** @var \App\Modules\Identity\Models\User $user */
        $user = auth()->user();

        $user->notifications()->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);

        $this->loadNotifications();
    }

    public function markAllRead(): void
    {
        /** @var \App\Modules\Identity\Models\User $user */
        $user = auth()->user();

        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        $this->loadNotifications();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.notification.notification-bell');
    }
}
