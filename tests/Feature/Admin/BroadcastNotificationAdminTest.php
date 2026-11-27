<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\BroadcastNotificationAdmin;
use App\Modules\Community\Models\BroadcastMessage;
use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
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

        $this->admin = $this->makeUser('ADMIN', 'admin@example.com');
        $this->superAdmin = $this->makeUser('SUPER_ADMIN', 'super@example.com');
    }

    private function makeUser(string $role, string $email): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => $email,
            'username' => explode('@', $email)[0],
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole($role);

        Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'cached_balance' => '0.00',
            'status' => WalletStatus::ACTIVE,
        ]);

        return $user;
    }

    private function makeBroadcast(array $overrides = []): BroadcastMessage
    {
        return BroadcastMessage::create(array_merge([
            'uuid' => Str::uuid()->toString(),
            'title' => 'Test Broadcast',
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
            'title' => 'Maintenance Tonight',
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
            'id' => $broadcast->id,
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

        $this->assertSoftDeleted('broadcast_messages', ['id' => $broadcast->id]);
        $this->assertNull(BroadcastMessage::find($broadcast->id));
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

    // ── What's New Popup Modal Announcements ────────────────────────────────

    public function test_admin_can_create_video_only_popup_announcement_without_body_message(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BroadcastNotificationAdmin::class)
            ->set('displayType', BroadcastMessage::DISPLAY_POPUP)
            ->set('targetSurface', BroadcastMessage::SURFACE_BOTH)
            ->set('badgeLabel', 'New Partnership')
            ->set('title', 'PlayStrategy x GamersRival Trailer')
            ->set('mediaType', BroadcastMessage::MEDIA_VIDEO)
            ->set('mediaUrl', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->set('isVideoOnly', true)
            ->set('message', '')
            ->set('ctaLabel', 'Play Now')
            ->set('ctaUrl', '/tournaments/browse')
            ->call('save')
            ->assertHasNoErrors();

        $popup = BroadcastMessage::query()->where('title', 'PlayStrategy x GamersRival Trailer')->firstOrFail();

        $this->assertSame(BroadcastMessage::DISPLAY_POPUP, $popup->display_type);
        $this->assertSame(BroadcastMessage::SURFACE_BOTH, $popup->target_surface);
        $this->assertTrue($popup->is_video_only);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&playsinline=1', $popup->embedVideoUrl());
        $this->assertSame('/tournaments/browse', $popup->safeCtaUrl());
    }

    public function test_popup_rejects_unsafe_javascript_urls(): void
    {
        Livewire::actingAs($this->admin)
            ->test(BroadcastNotificationAdmin::class)
            ->set('displayType', BroadcastMessage::DISPLAY_POPUP)
            ->set('title', 'Unsafe URL Test')
            ->set('message', 'Body text')
            ->set('ctaLabel', 'Click')
            ->set('ctaUrl', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors(['ctaUrl']);
    }

    public function test_popup_announcement_respects_surface_targeting_and_renders_on_homepage_and_dashboard(): void
    {
        $this->makeBroadcast([
            'title' => 'Homepage Exclusive Update',
            'message' => 'Visible on the homepage only.',
            'display_type' => BroadcastMessage::DISPLAY_POPUP,
            'target_surface' => BroadcastMessage::SURFACE_HOMEPAGE,
            'badge_label' => 'Update Is Here',
        ]);

        $this->makeBroadcast([
            'title' => 'Player Dashboard Partnership News',
            'message' => 'Visible on player dashboard.',
            'display_type' => BroadcastMessage::DISPLAY_BOTH,
            'target_surface' => BroadcastMessage::SURFACE_PLAYER_DASHBOARD,
            'badge_label' => 'New Partnership',
            'cta_label' => 'View Tournaments',
            'cta_url' => '/tournaments/browse',
        ]);

        $this->makeBroadcast([
            'title' => 'Expired Popup Should Hide',
            'message' => 'Old announcement.',
            'display_type' => BroadcastMessage::DISPLAY_POPUP,
            'target_surface' => BroadcastMessage::SURFACE_BOTH,
            'ends_at' => now()->subMinute(),
        ]);

        BroadcastMessage::clearCaches();

        // Guest Homepage receives only the homepage popup
        $this->get('/')
            ->assertOk()
            ->assertSee('data-announcement-popup-root', false)
            ->assertSee('Homepage Exclusive Update')
            ->assertDontSee('Player Dashboard Partnership News')
            ->assertDontSee('Expired Popup Should Hide');

        // Logged-in Player Dashboard receives only the dashboard popup
        $player = $this->makeUser('PLAYER', 'popup-player@example.com');
        $this->actingAs($player)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-announcement-popup-root', false)
            ->assertSee('Player Dashboard Partnership News')
            ->assertDontSee('Homepage Exclusive Update')
            ->assertDontSee('Expired Popup Should Hide');
    }
}
