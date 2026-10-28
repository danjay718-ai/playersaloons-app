<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\LandingPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandingV4Test extends TestCase
{
    use RefreshDatabase;

    public function test_v4_is_the_default_guest_landing_and_keeps_account_connections(): void
    {
        $this->seed(LandingPageSeeder::class);
        $game = Game::query()->create(['uuid' => Str::uuid()->toString(), 'slug' => 'valorant', 'is_active' => true]);
        $game->translations()->create(['locale' => 'en', 'name' => 'Valorant', 'description' => 'Shooter.']);

        $this->get('/')->assertOk()
            ->assertSee('gr-experience')
            ->assertSee('gr-home', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Home')
            ->assertSee('grTypewriter', false)
            ->assertSee('PLAY.')
            ->assertSee('WIN.')
            ->assertSee('CASH OUT.')
            ->assertSee('Tap a table to see what starts next.')
            ->assertSee('Valorant')
            ->assertSee('Your seat is open.')
            ->assertSee('href="/register"', false)
            ->assertSee('href="/login"', false)
            ->assertSee('data-pwa-install-desktop', false)
            ->assertSee('data-pwa-install-mobile', false)
            ->assertDontSee('guest-home', false);
    }

    public function test_v3_design_is_retained_behind_the_landing_version_flag(): void
    {
        config(['features.landing.version' => 'v3']);

        $this->get('/')->assertOk()->assertSee('guest-home', false)->assertSee('guest-experience')->assertDontSee('gr-home', false);
    }

    public function test_login_and_register_use_the_v4_palette_and_keep_install_actions(): void
    {
        foreach (['/login', '/register'] as $url) {
            $this->get($url)->assertOk()->assertSee('gr-experience')->assertSee('gr-footer', false)->assertSee('data-pwa-install-desktop', false);
        }
    }

    public function test_authenticated_players_still_see_the_authenticated_home(): void
    {
        $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $user->assignRole('PLAYER');

        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('gr-home', false);
    }
}
