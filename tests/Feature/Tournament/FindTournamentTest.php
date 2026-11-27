<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Livewire\Tournament\FindTournamentModal;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameCategory;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\ExternalProviderAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\FindActiveTournamentsService;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use Database\Seeders\LandingPageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FindTournamentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $player;

    private GameCategory $videoCategory;

    private GameCategory $boardCategory;

    private GameCategory $inactiveCategory;

    private Platform $pcPlatform;

    private Platform $webPlatform;

    private Game $valorant;

    private Game $chess;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.tournament_v2.enabled' => false]);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->admin->assignRole('ADMIN');

        $this->player = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->player->assignRole('PLAYER');

        $this->videoCategory = GameCategory::query()->firstOrCreate(
            ['slug' => 'video-games'],
            ['uuid' => Str::uuid()->toString(), 'name' => 'Video Games', 'display_order' => 1, 'is_active' => true]
        );
        $this->boardCategory = GameCategory::query()->firstOrCreate(
            ['slug' => 'card-board-games'],
            ['uuid' => Str::uuid()->toString(), 'name' => 'Card & Board Games', 'display_order' => 2, 'is_active' => true]
        );
        $this->inactiveCategory = GameCategory::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Archived Category',
            'slug' => 'archived-category',
            'display_order' => 99,
            'is_active' => false,
        ]);

        $this->pcPlatform = Platform::query()->firstOrCreate(
            ['slug' => 'pc'],
            ['name' => 'PC', 'is_active' => true]
        );
        $this->webPlatform = Platform::query()->firstOrCreate(
            ['slug' => 'web-browser'],
            ['name' => 'Web Browser', 'is_active' => true]
        );

        $this->valorant = $this->createGame('valorant', 'Valorant', $this->videoCategory, Game::PROVIDER_MANUAL);
        $this->chess = $this->createGame('playstrategy-chess', 'Chess', $this->boardCategory, Game::PROVIDER_PLAYSTRATEGY);
    }

    public function test_modal_runs_zero_database_queries_while_closed(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        Livewire::test(FindTournamentModal::class)
            ->assertSet('open', false)
            ->assertDontSee('Quick Matchmaker');

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_exact_matching_filters_and_ranking_order(): void
    {
        $soonFree = $this->createTournament([
            'name' => 'Valorant Flash Free Cup',
            'game_id' => $this->valorant->id,
            'platform_id' => $this->pcPlatform->id,
            'entry_fee' => '0.00',
            'max_participants' => 8,
            'start_at' => now()->addHour(),
        ]);

        $fillingFast = $this->createTournament([
            'name' => 'Valorant Filling Fast Cup',
            'game_id' => $this->valorant->id,
            'platform_id' => $this->pcPlatform->id,
            'entry_fee' => '0.00',
            'max_participants' => 4,
            'start_at' => now()->addHours(3),
        ]);
        // 3 of 4 seats filled (75%) -> ranks ahead of 0% filled
        for ($i = 0; $i < 3; $i++) {
            $entrant = User::factory()->create(['status' => UserStatus::ACTIVE]);
            $fillingFast->registrations()->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $entrant->id,
                'status' => 'confirmed',
                'payment_status' => 'free',
                'registered_at' => now(),
            ]);
        }

        $paidChessH2h = $this->createTournament([
            'name' => 'Chess High Stakes 1v1',
            'game_id' => $this->chess->id,
            'platform_id' => $this->webPlatform->id,
            'competition_type' => CompetitionType::HEAD_TO_HEAD,
            'entry_fee' => '25.00',
            'max_participants' => 2,
            'start_at' => now()->addHours(2),
        ]);

        $service = app(FindActiveTournamentsService::class);

        // Free PC Video Games -> fillingFast ranked before soonFree
        $freeVideo = $service->find([
            'category' => 'video-games',
            'platform_id' => (string) $this->pcPlatform->id,
            'budget' => 'free',
        ]);
        $this->assertTrue($freeVideo['exact']);
        $this->assertNull($freeVideo['fallback_reason']);
        $this->assertSame(2, $freeVideo['total_matching']);
        $this->assertSame([$fillingFast->id, $soonFree->id], $freeVideo['tournaments']->pluck('id')->all());

        // Starting soon (< 2h) narrows to soonFree
        $soon = $service->find(['game_id' => (string) $this->valorant->id, 'time_window' => 'soon']);
        $this->assertTrue($soon['exact']);
        $this->assertSame([$soonFree->id], $soon['tournaments']->pluck('id')->all());

        // High stakes H2H matches Chess only
        $highH2h = $service->find([
            'budget' => 'high',
            'competition_type' => 'head_to_head',
        ]);
        $this->assertTrue($highH2h['exact']);
        $this->assertSame([$paidChessH2h->id], $highH2h['tournaments']->pluck('id')->all());
    }

    public function test_zero_exact_matches_returns_closest_open_fallback_and_excludes_ineligible(): void
    {
        $openChessFree = $this->createTournament([
            'name' => 'Open Chess Free Table',
            'game_id' => $this->chess->id,
            'platform_id' => $this->webPlatform->id,
            'entry_fee' => '0.00',
            'max_participants' => 8,
            'start_at' => now()->addHours(2),
        ]);

        // Ineligible 1: inactive category
        $archivedGame = $this->createGame('archived-game', 'Archived Game', $this->inactiveCategory);
        $this->createTournament(['name' => 'Hidden Inactive Category Cup', 'game_id' => $archivedGame->id]);

        // Ineligible 2: disabled game
        $disabledGame = $this->createGame('disabled-game', 'Disabled Game', $this->videoCategory, Game::PROVIDER_MANUAL, false);
        $this->createTournament(['name' => 'Hidden Disabled Game Cup', 'game_id' => $disabledGame->id]);

        // Ineligible 3: soft-deleted game
        $deletedGame = $this->createGame('deleted-game', 'Deleted Game', $this->videoCategory);
        $this->createTournament(['name' => 'Hidden Deleted Game Cup', 'game_id' => $deletedGame->id]);
        $deletedGame->delete();

        // Ineligible 4: full tournament
        $fullTournament = $this->createTournament([
            'name' => 'Full Chess Cup',
            'game_id' => $this->chess->id,
            'entry_fee' => '30.00',
            'max_participants' => 2,
        ]);
        for ($i = 0; $i < 2; $i++) {
            $entrant = User::factory()->create(['status' => UserStatus::ACTIVE]);
            $fullTournament->registrations()->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $entrant->id,
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'registered_at' => now(),
            ]);
        }

        // Ineligible 5: past start_at & closed status
        $this->createTournament([
            'name' => 'Past Start Cup',
            'game_id' => $this->chess->id,
            'entry_fee' => '30.00',
            'start_at' => now()->subMinutes(10),
        ]);
        $this->createTournament([
            'name' => 'Ongoing Cup',
            'game_id' => $this->chess->id,
            'entry_fee' => '30.00',
            'status' => TournamentStatus::ONGOING,
        ]);

        // Request high-stakes Chess -> 0 exact matches -> falls back to Open Chess Free Table ONLY
        $result = app(FindActiveTournamentsService::class)->find([
            'game_id' => (string) $this->chess->id,
            'budget' => 'high',
        ]);

        $this->assertFalse($result['exact']);
        $this->assertSame('same_game', $result['fallback_reason']);
        $this->assertSame([$openChessFree->id], $result['tournaments']->pluck('id')->all());

        // Modal displays fallback notice cleanly
        Livewire::test(FindTournamentModal::class)
            ->call('openModal')
            ->set('gameId', (string) $this->chess->id)
            ->set('budget', 'high')
            ->assertSee('No exact matches for your filters')
            ->assertSee('Open Chess Free Table')
            ->assertDontSee('Hidden Inactive Category Cup')
            ->assertDontSee('Hidden Disabled Game Cup')
            ->assertDontSee('Hidden Deleted Game Cup')
            ->assertDontSee('Full Chess Cup');
    }

    public function test_modal_renders_guest_links_and_playstrategy_connect_prompt_for_players(): void
    {
        $chessOpen = $this->createTournament([
            'name' => 'PlayStrategy Daily Blitz',
            'game_id' => $this->chess->id,
            'platform_id' => $this->webPlatform->id,
            'entry_fee' => '0.00',
        ]);

        // Before applying any filter, modal prompts user to filter and does not dump all tournaments yet
        Livewire::test(FindTournamentModal::class)
            ->call('openModal')
            ->assertSee('Start with a filter')
            ->assertDontSee('PlayStrategy Daily Blitz');

        // Guest sees View & Join link once a filter is applied
        Livewire::test(FindTournamentModal::class)
            ->call('openModal')
            ->set('category', 'card-board-games')
            ->assertSee('PlayStrategy Daily Blitz')
            ->assertSee('View & Join')
            ->assertSee('/tournaments/'.$chessOpen->uuid.'/view?view=guest', false);

        // Logged-in player without PlayStrategy linked sees Connect PlayStrategy prompt
        Livewire::actingAs($this->player)
            ->test(FindTournamentModal::class)
            ->call('openModal')
            ->set('budget', 'free')
            ->assertSee('PlayStrategy Daily Blitz')
            ->assertSee('Connect PlayStrategy')
            ->assertSee('/profile?tab=settings', false);

        // Once PlayStrategy is linked, player gets direct Join Now CTA
        ExternalProviderAccount::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->player->id,
            'provider' => ExternalProviderAccount::PROVIDER_PLAYSTRATEGY,
            'external_user_id' => 'ps-user-1',
            'external_username' => 'GrandmasterTest',
            'access_token' => 'secret-token-value',
            'verified_at' => now(),
        ]);

        Livewire::actingAs($this->player)
            ->test(FindTournamentModal::class)
            ->call('openModal')
            ->set('gameId', (string) $this->chess->id)
            ->assertSee('Join Now')
            ->assertDontSee('Connect PlayStrategy');
    }

    public function test_entry_triggers_render_on_homepage_and_player_dashboard(): void
    {
        $this->seed(LandingPageSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('open-find-tournament', false)
            ->assertSee('Find Tournament');

        $this->actingAs($this->player)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('open-find-tournament', false);
    }

    public function test_filter_options_return_plain_scalar_arrays_safe_without_php_object_unserialization(): void
    {
        $options = app(FindActiveTournamentsService::class)->filterOptions('card-board-games');

        $serialized = serialize($options);
        $restored = unserialize($serialized, ['allowed_classes' => false]);

        $this->assertIsArray($restored);
        $this->assertNotEmpty($restored['categories']);
        $this->assertCount(1, $restored['games']);
        $this->assertSame('Chess', $restored['games'][0]['name']);
        $this->assertNotEmpty($restored['platforms']);
    }

    private function createGame(
        string $slug,
        string $name,
        GameCategory $category,
        string $provider = Game::PROVIDER_MANUAL,
        bool $active = true,
    ): Game {
        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => $slug,
            'game_category_id' => $category->id,
            'gameplay_provider' => $provider,
            'is_active' => $active,
        ]);

        $game->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'description' => $name.' description',
        ]);

        return $game;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTournament(array $overrides = []): Tournament
    {
        $name = (string) ($overrides['name'] ?? 'Test Cup '.Str::random(5));

        return Tournament::query()->create(array_merge([
            'uuid' => Str::uuid()->toString(),
            'workflow_version' => 1,
            'game_id' => $this->valorant->id,
            'competition_type' => CompetitionType::TOURNAMENT,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'status' => TournamentStatus::REGISTRATION_OPEN,
            'entry_fee' => '0.00',
            'prize_pool' => '50.00',
            'max_participants' => 16,
            'min_participants' => 2,
            'start_at' => now()->addHours(2),
            'created_by' => $this->admin->id,
        ], $overrides));
    }
}
