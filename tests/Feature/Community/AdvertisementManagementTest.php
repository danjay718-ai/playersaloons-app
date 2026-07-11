<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Livewire\Admin\AdvertisementAdmin;
use App\Modules\Community\Models\Advertisement;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdvertisementManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::query()->create(['uuid' => Str::uuid(), 'email' => strtolower($role).'@example.com', 'username' => strtolower($role), 'password' => 'password', 'status' => UserStatus::ACTIVE, 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_create_scheduled_player_promotion(): void
    {
        $admin = $this->user('ADMIN');

        Livewire::actingAs($admin)->test(AdvertisementAdmin::class)
            ->set('title', 'Weekend Bonus')->set('description', 'Join this weekend event.')
            ->set('targetUrl', 'https://example.com/promo')->set('ctaLabel', 'View event')->set('isActive', true)
            ->call('save')->assertHasNoErrors()->assertSee('Advertisement saved.');

        $this->assertDatabaseHas('advertisements', ['title' => 'Weekend Bonus', 'is_active' => true, 'created_by' => $admin->id]);
        $this->assertSame('Weekend Bonus', Advertisement::query()->currentlyVisible()->firstOrFail()->title);
    }

    public function test_future_and_expired_promotions_are_not_visible(): void
    {
        $admin = $this->user('ADMIN');
        foreach ([[now()->addDay(), null], [now()->subDays(2), now()->subDay()]] as $index => [$starts, $ends]) {
            Advertisement::query()->create(['uuid' => Str::uuid(), 'title' => 'Hidden '.$index, 'is_active' => true, 'starts_at' => $starts, 'ends_at' => $ends, 'created_by' => $admin->id]);
        }

        $this->assertSame(0, Advertisement::query()->currentlyVisible()->count());
    }

    public function test_promotion_click_redirects_and_tracks_click(): void
    {
        $admin = $this->user('ADMIN');
        $ad = Advertisement::query()->create(['uuid' => Str::uuid(), 'title' => 'Clickable', 'target_url' => 'https://example.com/offer', 'is_active' => true, 'created_by' => $admin->id]);

        $this->get(route('promotions.click', $ad))->assertRedirect('https://example.com/offer');
        $this->assertSame(1, $ad->fresh()?->clicks);
    }

    public function test_non_admin_staff_cannot_manage_advertisements(): void
    {
        $support = $this->user('SUPPORT_AGENT');
        $this->actingAs($support)->get('/admin/advertisements')->assertForbidden();
    }
}
