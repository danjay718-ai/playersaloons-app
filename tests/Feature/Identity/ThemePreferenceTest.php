<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Livewire\Identity\ThemeSwitcher;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserTheme;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_new_accounts_use_the_neon_arena_theme(): void
    {
        $user = User::factory()->create()->refresh();

        $this->assertSame(UserTheme::NEON_ARENA, $user->theme);
    }

    public function test_authenticated_user_can_persist_a_shared_theme_preference(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ThemeSwitcher::class)
            ->call('setTheme', UserTheme::LIGHT->value)
            ->assertSet('theme', UserTheme::LIGHT->value)
            ->assertHasNoErrors()
            ->assertDispatched('theme-preference-updated');

        $this->assertSame(UserTheme::LIGHT, $user->refresh()->theme);
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
            ->assertSeeHtml('id="desktop-sidebar"')
            ->assertSeeHtml('class="theme-sidebar group/sidebar')
            ->assertDontSeeHtml('id="desktop-sidebar" class="group/sidebar hidden md:flex fixed top-0 left-0 h-screen bg-[#0a0718]/90')
            ->assertSee('Choose color theme');

        $admin = User::factory()->create(['theme' => UserTheme::NEON_ARENA]);
        $admin->syncRoles(['ADMIN']);

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSeeHtml('data-theme="neon_arena"')
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
            ->assertSee('Neon Arena')
            ->assertSee('Light');

        $admin = User::factory()->create();
        $admin->syncRoles(['ADMIN']);

        $this->actingAs($admin)
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Appearance')
            ->assertSee('Neon Arena')
            ->assertSee('Light');
    }

    public function test_removed_blue_and_violet_themes_are_rejected_and_legacy_rows_fall_back(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ThemeSwitcher::class)
            ->call('setTheme', 'blue_dark')
            ->assertHasErrors(['theme'])
            ->call('setTheme', 'purple_dark')
            ->assertHasErrors(['theme']);

        DB::table('users')->where('id', $user->id)->update(['theme' => 'purple_dark']);
        $this->assertSame(UserTheme::NEON_ARENA, $user->fresh()->theme);
    }

    public function test_theme_switcher_lives_in_the_profile_menu_not_the_topbar(): void
    {
        $player = User::factory()->create();
        $player->syncRoles(['PLAYER']);

        $this->actingAs($player)->get('/dashboard')->assertOk()->assertSeeHtml('player-header-theme', false);
    }

    public function test_guest_landing_page_keeps_its_existing_public_theme(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSeeHtml('data-theme="');
    }
}
