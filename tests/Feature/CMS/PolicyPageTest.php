<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use App\Livewire\Admin\PolicyAdmin;
use App\Modules\CMS\Models\PolicyPage;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\PolicyPageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PolicyPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PolicyPageSeeder::class);
    }

    public function test_guest_can_view_policy_index_and_seeded_policy_page(): void
    {
        $this->get('/policies')
            ->assertOk()
            ->assertSee('Policies')
            ->assertSee('Terms and Conditions')
            ->assertSee('Cookie Policy')
            ->assertSee('Privacy Policy')
            ->assertSee('Refund and Cancellation Policy')
            ->assertSee('Disclaimer');

        $this->get('/policies/privacy-policy')
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('What information we collect')
            ->assertSee('PlayerSaloons collects account');

        $this->get('/policies/terms-and-conditions')
            ->assertOk()
            ->assertSee('Terms and Conditions')
            ->assertSee('The rules for accessing and using PlayerSaloons services');
    }

    public function test_inactive_or_unpublished_policy_page_returns_not_found(): void
    {
        PolicyPage::query()->where('slug', 'disclaimer')->update([
            'is_active' => false,
        ]);

        $this->get('/policies/disclaimer')->assertNotFound();

        PolicyPage::query()->where('slug', 'privacy-policy')->update([
            'published_at' => null,
        ]);

        $this->get('/policies/privacy-policy')->assertNotFound();
    }

    public function test_admin_can_update_policy_page_content(): void
    {
        $admin = $this->adminUser();
        $policy = PolicyPage::query()->where('slug', 'cookie-policy')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(PolicyAdmin::class)
            ->call('selectPolicy', $policy->id)
            ->set('title', 'Cookie Notice')
            ->set('slug', 'cookie-notice')
            ->set('summary', 'Updated browser storage notice.')
            ->set('content', '<p>Updated <strong>cookie notice</strong> body with enough detail for validation.</p>')
            ->set('sortOrder', 8)
            ->set('isActive', true)
            ->set('isPublished', true)
            ->call('savePolicy')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('policy_pages', [
            'id' => $policy->id,
            'title' => 'Cookie Notice',
            'slug' => 'cookie-notice',
            'summary' => 'Updated browser storage notice.',
            'content' => '<p>Updated <strong>cookie notice</strong> body with enough detail for validation.</p>',
            'sort_order' => 8,
            'is_active' => true,
            'updated_by' => $admin->id,
        ]);

        $this->get('/policies/cookie-notice')
            ->assertOk()
            ->assertSee('Cookie Notice')
            ->assertSee('Updated')
            ->assertSee('<strong>cookie notice</strong>', false);
    }

    public function test_player_cannot_access_policy_admin(): void
    {
        /** @var User $player */
        $player = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $player->assignRole('PLAYER');

        $this->actingAs($player)
            ->get('/admin/policies')
            ->assertForbidden();
    }

    private function adminUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole('ADMIN');

        return $user;
    }
}
