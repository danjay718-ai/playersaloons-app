<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\BroadcastNotificationAdmin;
use App\Modules\Community\Models\BroadcastMessage;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use App\Modules\Wallet\Models\Wallet;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class BroadcastNotificationAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        $this->admin      = $this->makeUser('ADMIN', 'admin@example.com');
        $this->superAdmin = $this->makeUser('SUPER_ADMIN', 'super@example.com');
    }

    private function makeUser(string $role, string $email): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid'              => Str::uuid()->toString(),
            'email'             => $email,
            'username'          => explode('@', $email)[0],
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
            'status'            => UserStatus::ACTIVE,
        ]);
        $user->assignRole($role);

        Wallet::query()->create([
            'uuid'           => Str::uuid()->toString(),
            'user_id'        => $user->id,
            'cached_balance' => '0.00',
            'status'         => WalletStatus::ACTIVE,
        ]);

        return $user;
    }

    private function makeBroadcast(array $overrides = []): BroadcastMessage
    {
        return BroadcastMessage::create(array_merge([
            'uuid'    => Str::uuid()->toString(),
            'title'   => 'Test Broadcast',
            'message' => 'Test message content.',
        ], $overrides));
    }

    // ── Access ───────────────────────────────────────────────────────────────

    public function test_admin_can_access_notifications_panel(): void
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->admin)->get('/admin/notifications')->assertOk();
    }

    public function test_player_cannot_access_notifications_panel(): void
    {
        $player = $this->makeUser('PLAYER', 'player@example.com');
        $this->actingAs($player)->get('/admin/notifications')->assertStatus(403);
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_admin_can_create_broadcast(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BroadcastNotificationAdmin::class)
            ->set('title', 'Maintenance Tonight')
            ->set('message', 'Platform will be down from 2-4 AM.')
            ->call('save');

        $this->assertDatabaseHas('broadcast_messages', [
            'title'   => 'Maintenance Tonight',
            'message' => 'Platform will be down from 2-4 AM.',
        ]);
    }

    public function test_create_broadcast_requires_title_and_message(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BroadcastNotificationAdmin::class)
            ->set('title', '')
            ->set('message', '')
            ->call('save')
            ->assertHasErrors(['title', 'message']);
    }

    // ── Edit ─────────────────────────────────────────────────────────────────

    public function test_admin_can_edit_broadcast(): void
    {
        $broadcast = $this->makeBroadcast();

        Livewire::actingAs($this->admin)
            ->test(BroadcastNotificationAdmin::class)
            ->call('openEdit', $broadcast->id)
            ->assertSet('title', 'Test Broadcast')
            ->set('title', 'Updated Title')
            ->call('save');

        $this->assertDatabaseHas('broadcast_messages', [
            'id'    => $broadcast->id,
            'title' => 'Updated Title',
        ]);
    }

    // ── Expire ───────────────────────────────────────────────────────────────

    public function test_admin_can_expire_broadcast(): void
    {
        $broadcast = $this->makeBroadcast();

        Livewire::actingAs($this->admin)
            ->test(BroadcastNotificationAdmin::class)
            ->call('confirmExpire', $broadcast->id)
            ->call('executeConfirm');

        $this->assertNotNull(BroadcastMessage::find($broadcast->id)?->ends_at);
    }

    // ── Delete (SUPER_ADMIN only) ─────────────────────────────────────────────

    public function test_super_admin_can_delete_broadcast(): void
    {
        $broadcast = $this->makeBroadcast();

        Livewire::actingAs($this->superAdmin)
            ->test(BroadcastNotificationAdmin::class)
            ->call('confirmDelete', $broadcast->id)
            ->call('executeConfirm');

        $this->assertDatabaseMissing('broadcast_messages', ['id' => $broadcast->id]);
    }

    public function test_admin_cannot_delete_broadcast(): void
    {
        $broadcast = $this->makeBroadcast();

        Livewire::actingAs($this->admin)
            ->test(BroadcastNotificationAdmin::class)
            ->call('confirmDelete', $broadcast->id)
            ->assertStatus(403);

        $this->assertDatabaseHas('broadcast_messages', ['id' => $broadcast->id]);
    }

    // ── Search ───────────────────────────────────────────────────────────────

    public function test_search_filters_broadcasts(): void
    {
        $this->makeBroadcast(['title' => 'Maintenance Window']);
        $this->makeBroadcast(['title' => 'New Feature Release']);

        Livewire::actingAs($this->admin)
            ->test(BroadcastNotificationAdmin::class)
            ->set('search', 'Maintenance')
            ->assertViewHas('broadcasts', fn ($p) => $p->total() === 1);
    }
}
