<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use App\Livewire\Admin\CmsAdmin;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\LandingSection;
use App\Modules\CMS\Models\PublicNavigationItem;
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

    public function test_authenticated_public_pages_keep_the_new_navigation_and_footer(): void
    {
        $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $user->assignRole('PLAYER');
        $this->seed(LandingPageSeeder::class);
        foreach (['/tournaments', '/h2h?view=guest', '/about', '/contact', '/updates'] as $url) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('gr-experience')->assertSee('gr-header')->assertSee('gr-footer')
                ->assertDontSee('id="public-nav"', false);
        }
        PublicNavigationItem::query()->where('url', '/')->update(['label' => 'Start here']);
        $this->get('/about')->assertOk()->assertSee('Start here');
        PublicNavigationItem::query()->where('url', '/')->update(['is_active' => false]);
        $this->get('/about')->assertOk()->assertDontSee('Start here');
    }

    public function test_hero_rotation_has_a_visible_editor_and_saves_additions_removals_and_order(): void
    {
        $this->seed(LandingPageSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $admin->assignRole('ADMIN');
        $hero = LandingSection::query()->where('key', 'hero')->firstOrFail();
        $editor = Livewire::actingAs($admin)->test(CmsAdmin::class, ['section' => 'landing'])
            ->call('selectLandingSection', $hero->id)
            ->assertSee('Hero rotating phrases')
            ->assertSet('landingHeroRotatingWords', "DOMINATE.\nGET PAID.\nRANK UP.")
            ->set('landingHeroRotatingWords', "PLAY TOGETHER.\nWIN TOGETHER.\nCLIMB.\nCELEBRATE.")
            ->call('saveLandingSection')->assertHasNoErrors();
        $this->get('/')->assertOk()->assertSeeInOrder(['PLAY TOGETHER.', 'WIN TOGETHER.', 'CLIMB.', 'CELEBRATE.'])->assertDontSee('DOMINATE.');
        $rotation = $hero->items()->where('item_key', 'rotate-dominate')->firstOrFail();
        $editor->call('openEditLandingItemModal', $rotation->id)
            ->set('landingItemTitle', 'EDITED.')
            ->call('saveLandingItem')
            ->assertSet('landingHeroRotatingWords', "EDITED.\nWIN TOGETHER.\nCLIMB.\nCELEBRATE.")
            ->call('toggleLandingItemActive', $rotation->id)
            ->assertSet('landingHeroRotatingWords', "WIN TOGETHER.\nCLIMB.\nCELEBRATE.");
        $editor->set('landingHeroRotatingWords', 'ONE PHRASE.')->call('saveLandingSection')->assertHasNoErrors();
        $this->get('/')->assertOk()->assertSee('ONE PHRASE.')->assertDontSee('WIN TOGETHER.');
        $editor->set('landingHeroRotatingWords', str_repeat('x', 256))->call('saveLandingSection')->assertHasErrors('landingHeroRotatingWords');
        $editor->set('landingHeroRotatingWords', '')->call('saveLandingSection')->assertHasNoErrors();
        $this->get('/')->assertOk()->assertDontSee('ONE PHRASE.')->assertDontSee('GET PAID.');
    }

    public function test_footer_shortcuts_and_copy_backfill_preserve_admin_changes(): void
    {
        $this->seed(LandingPageSeeder::class);
        $home = PublicNavigationItem::query()->where('url', '/')->firstOrFail();
        $home->update(['label' => 'Start here', 'is_active' => false]);
        $home->delete();
        $footer = LandingSection::query()->where('key', 'footer')->firstOrFail();
        $h2h = $footer->items()->where('item_key', 'competition-head-to-head')->firstOrFail();
        $h2h->update(['is_active' => false]);
        $footer->items()->where('item_key', 'about-us')->update(['title' => 'Who we are', 'url' => '/about?source=footer']);

        $migration = require database_path('migrations/2026_11_05_000100_add_managed_public_copy.php');
        $migration->up();
        $this->assertTrue($home->fresh()->trashed());
        $this->assertSame('Start here', $home->fresh()->label);
        $this->assertFalse($h2h->fresh()->is_active);
        $this->view('components.layouts.partials.gr-footer')
            ->assertSee('Who we are')->assertSee('/about?source=footer')
            ->assertDontSee('href="/h2h?view=guest"', false);
    }
}
