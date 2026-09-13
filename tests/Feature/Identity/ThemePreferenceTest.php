<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Livewire\Identity\ThemeSwitcher;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserTheme;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_new_accounts_use_the_original_purple_theme(): void
    {
        $user = User::factory()->create()->refresh();

        $this->assertSame(UserTheme::PURPLE_DARK, $user->theme);
    }

    public function test_authenticated_user_can_persist_a_shared_theme_preference(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ThemeSwitcher::class)
            ->call('setTheme', UserTheme::BLUE_DARK->value)
            ->assertSet('theme', UserTheme::BLUE_DARK->value)
            ->assertHasNoErrors()
            ->assertDispatched('theme-preference-updated');

        $this->assertSame(UserTheme::BLUE_DARK, $user->refresh()->theme);
    }

    public function test_invalid_theme_is_rejected_without_changing_the_account(): void
    {
        $user = User::factory()->create(['theme' => UserTheme::LIGHT]);

        Livewire::actingAs($user)
            ->test(ThemeSwitcher::class)
            ->call('setTheme', 'unsupported-theme')
            ->assertHasErrors(['theme']);

        $this->assertSame(UserTheme::LIGHT, $user->refresh()->theme);
    }

    public function test_saved_theme_is_rendered_on_player_and_admin_layouts(): void
    {
        $player = User::factory()->create(['theme' => UserTheme::LIGHT]);
        $player->syncRoles(['PLAYER']);

        $this->actingAs($player)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeHtml('data-theme="light"')
            ->assertSeeHtml('id="desktop-sidebar" class="theme-sidebar')
            ->assertDontSeeHtml('id="desktop-sidebar" class="group/sidebar hidden md:flex fixed top-0 left-0 h-screen bg-[#0a0718]/90')
            ->assertSee('Choose color theme');

        $admin = User::factory()->create(['theme' => UserTheme::BLUE_DARK]);
        $admin->syncRoles(['ADMIN']);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeHtml('data-theme="blue_dark"')
            ->assertSee('Choose color theme');
    }

    public function test_theme_choices_are_available_in_both_profile_areas(): void
    {
        $player = User::factory()->create();
        $player->syncRoles(['PLAYER']);

        $this->actingAs($player)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Arena Theme')
            ->assertSee('Blue Dark')
            ->assertSee('Light');

        $admin = User::factory()->create();
        $admin->syncRoles(['ADMIN']);

        $this->actingAs($admin)
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Appearance')
            ->assertSee('Blue Dark')
            ->assertSee('Light');
    }

    public function test_guest_landing_page_keeps_its_existing_public_theme(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSeeHtml('data-theme="');
    }
}
