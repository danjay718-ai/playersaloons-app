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

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LandingPageSeeder::class);
    }

    public function test_landing_page_renders_seeded_content_video_and_games(): void
    {
        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant',
            'is_active' => true,
        ]);
        $game->translations()->create([
            'locale' => 'en',
            'name' => 'Valorant',
            'description' => 'Tactical competitive shooter.',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('/compressed_v1.mp4')
            ->assertSee('PLAY. WIN. CASH OUT.')
            ->assertSee('Available Games')
            ->assertSee('Valorant')
            ->assertSee('How It Works')
            ->assertSee('Live Platform Stats')
            ->assertSee('Top Players This Week')
            ->assertSee('Built For Competitive Play')
            ->assertSee('Player Reviews');
    }

    public function test_admin_can_update_landing_section_and_create_item(): void
    {
        $admin = $this->adminUser();
        $hero = LandingSection::query()->where('key', 'hero')->firstOrFail();
        $features = LandingSection::query()->where('key', 'features')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(CmsAdmin::class)
            ->call('setTab', 'landing')
            ->call('selectLandingSection', $hero->id)
            ->set('landingSectionTitle', 'NEW HERO COPY')
            ->set('landingSectionMediaPath', '/compressed_v1.mp4')
            ->call('saveLandingSection')
            ->assertHasNoErrors()
            ->call('openCreateLandingItemModal', $features->id)
            ->set('landingItemKey', 'streaming')
            ->set('landingItemTitle', 'Streaming')
            ->set('landingItemBody', 'Watch featured competitive streams.')
            ->set('landingItemIcon', 'radio')
            ->set('landingItemUrl', '/streams')
            ->set('landingItemSortOrder', 99)
            ->call('saveLandingItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('landing_sections', [
            'id' => $hero->id,
            'title' => 'NEW HERO COPY',
            'media_path' => '/compressed_v1.mp4',
        ]);

        $this->assertDatabaseHas('landing_section_items', [
            'landing_section_id' => $features->id,
            'item_key' => 'streaming',
            'title' => 'Streaming',
            'url' => '/streams',
        ]);
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
