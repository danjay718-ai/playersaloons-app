<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Livewire\Notification\NotificationBell;
use App\Modules\Community\Models\Notification;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $user->assignRole('PLAYER');
        return $user;
    }

    private function makeNotification(User $user, bool $read = false): Notification
    {
        /** @var Notification $notification */
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'read_at' => $read ? now() : null,
        ]);
        return $notification;
    }

    public function test_bell_shows_unread_count(): void
    {
        $user = $this->makeUser();
        $this->makeNotification($user);
        $this->makeNotification($user);
        $this->makeNotification($user, read: true);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSet('unreadCount', 2);
    }

    public function test_bell_loads_notifications(): void
    {
        $user = $this->makeUser();
        $n = $this->makeNotification($user);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->assertSee($n->title);
    }

    public function test_mark_as_read_marks_single_notification(): void
    {
        $user = $this->makeUser();
        $n = $this->makeNotification($user);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAsRead', $n->id)
            ->assertSet('unreadCount', 0);

        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_all_read_clears_all_unread(): void
    {
        $user = $this->makeUser();
        $this->makeNotification($user);
        $this->makeNotification($user);

        Livewire::actingAs($user)
            ->test(NotificationBell::class)
            ->call('markAllRead')
            ->assertSet('unreadCount', 0);

        $this->assertEquals(0, $user->notifications()->whereNull('read_at')->count());
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $n = $this->makeNotification($userB);

        Livewire::actingAs($userA)
            ->test(NotificationBell::class)
            ->call('markAsRead', $n->id);

        // userB's notification should remain unread
        $this->assertNull($n->fresh()->read_at);
    }

    public function test_notification_received_event_refreshes_list(): void
    {
        $user = $this->makeUser();

        $component = Livewire::actingAs($user)->test(NotificationBell::class);
        $component->assertSet('unreadCount', 0);

        // Simulate a new notification arriving
        $this->makeNotification($user);

        $component->dispatch('notification.received')
            ->assertSet('unreadCount', 1);
    }
}
