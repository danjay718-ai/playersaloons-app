<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use App\Livewire\Admin\CmsAdmin;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\LandingSection;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\LandingPageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
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
            ->assertDontSee('V2 Saloon')
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
            ->assertDontSee('guest-home', false)
            ->assertDontSee('data-pwa-update-prompt', false)
            ->assertDontSee('GamersRival update ready');
    }

    public function test_v3_design_is_retained_behind_the_landing_version_flag(): void
    {
        config(['features.landing.version' => 'v3']);

        $this->get('/')->assertOk()->assertSee('guest-home', false)->assertSee('guest-experience')->assertDontSee('gr-home', false);
    }

    public function test_login_and_register_use_the_v4_palette_and_keep_install_actions(): void
    {
        foreach (['/login', '/register'] as $url) {
            $this->get($url)->assertOk()->assertSee('gr-experience')->assertSee('gr-footer', false)->assertSee('data-pwa-install-desktop', false)->assertDontSee('data-pwa-update-prompt', false);
        }
    }

    public function test_authenticated_players_see_the_same_landing_with_dashboard_access(): void
    {
        $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $user->assignRole('PLAYER');

        $this->seed(LandingPageSeeder::class);
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('gr-home', false)->assertSee('gr-experience')->assertSee('gr-footer', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('V2 Saloon')->assertDontSee('landing-page-root')->assertDontSee('href="/register"', false);
    }

    public function test_new_landing_copy_can_be_saved_in_the_existing_editor(): void
    {
        $this->seed(LandingPageSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $admin->assignRole('ADMIN');
        $editor = Livewire::actingAs($admin)->test(CmsAdmin::class)->call('setTab', 'landing');

        foreach (['hero', 'games', 'cta', 'competition_empty', 'leaderboard_empty', 'faq', 'activity'] as $key) {
            $section = LandingSection::query()->where('key', $key)->firstOrFail();
            $editor->call('selectLandingSection', $section->id)
                ->set('landingSectionTitle', 'Edited '.$key)
                ->set('landingSectionSubtitle', 'Subtitle '.$key)
                ->set('landingSectionBody', 'Body '.$key)
                ->set('landingSectionCtaLabel', 'Action '.$key)
                ->set('landingSectionCtaUrl', '/tournaments?source='.$key)
                ->call('saveLandingSection')->assertHasNoErrors();
        }
        $hero = LandingSection::query()->where('key', 'hero')->firstOrFail();
        $rotation = $hero->items()->where('item_key', 'rotate-dominate')->firstOrFail();
        $editor->call('openEditLandingItemModal', $rotation->id)
            ->set('landingItemTitle', 'CUSTOM ROTATION.')
            ->call('saveLandingItem')->assertHasNoErrors();
        $faq = LandingSection::query()->where('key', 'faq')->firstOrFail();
        $question = $faq->items()->firstOrFail();
        $editor->call('openEditLandingItemModal', $question->id)
            ->set('landingItemTitle', 'Custom question?')->set('landingItemBody', 'Custom answer.')
            ->call('saveLandingItem')->assertHasNoErrors();
        $game = Game::query()->create(['uuid' => Str::uuid()->toString(), 'slug' => 'editable', 'is_active' => true]);
        $game->translations()->create(['locale' => 'en', 'name' => 'Editable game']);

        $this->get('/')->assertOk()->assertSee('Body hero')->assertSee('Subtitle hero')
            ->assertSee('Body activity')->assertSee('Body games')->assertSee('Action games')->assertSee('Edited cta')
            ->assertSee('Body cta')->assertSee('Edited competition_empty')->assertSee('Body leaderboard_empty')
            ->assertSee('Custom question?')->assertSee('Custom answer.')->assertSee('CUSTOM ROTATION.')
            ->assertDontSee('How do tournament payouts work?');
    }

    public function test_hiding_sections_and_faq_items_does_not_restore_hardcoded_content(): void
    {
        $this->seed(LandingPageSeeder::class);
        LandingSection::query()->whereIn('key', ['hero', 'games', 'cta', 'faq', 'activity'])->update(['is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee('data-gr-hero', false)
            ->assertDontSee('id="games"', false)->assertDontSee('gr-cta-actions', false)
            ->assertDontSee('The next win could be yours.')->assertDontSee('id="faq"', false)->assertDontSee('gr-joinbar', false);
        $faq = LandingSection::query()->where('key', 'faq')->firstOrFail();
        $faq->update(['is_active' => true]);
        $faq->items()->update(['is_active' => false]);
        $this->get('/')->assertOk()->assertDontSee('How do tournament payouts work?');
    }
}
