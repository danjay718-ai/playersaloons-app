<?php

declare(strict_types=1);

namespace Tests\Feature\CMS;

use App\Livewire\Admin\CmsAdmin;
use App\Modules\CMS\Actions\DeleteGameCategoryAction;
use App\Modules\CMS\Actions\ReorderGameCategoryAction;
use App\Modules\CMS\Actions\SaveGameCategoryAction;
use App\Modules\CMS\Actions\ToggleGameCategoryStatusAction;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameCategory;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\Services\AdminV2ScheduleQuery;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use Database\Seeders\GamesTableSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class GameCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $player;

    private User $supportAgent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->admin->assignRole('ADMIN');

        $this->player = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->player->assignRole('PLAYER');

        $this->supportAgent = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->supportAgent->assignRole('SUPPORT_AGENT');
    }

    public function test_migration_seeds_initial_categories_and_backfills_existing_and_soft_deleted_games(): void
    {
        $categories = GameCategory::query()->ordered()->get();

        $this->assertCount(2, $categories);
        $this->assertSame(['Video Games', 'Card & Board Games'], $categories->pluck('name')->all());
        $this->assertSame([GameCategory::VIDEO_GAMES_SLUG, GameCategory::CARD_BOARD_GAMES_SLUG], $categories->pluck('slug')->all());
        $this->assertTrue($categories->every(fn (GameCategory $cat): bool => $cat->is_active));

        // Simulate pre-migration state by rolling back the migration, inserting an active
        // and soft-deleted game without game_category_id, then re-running the migration.
        $migration = require database_path('migrations/2026_11_24_000001_create_game_categories_table.php');
        $migration->down();

        $activeId = DB::table('games')->insertGetId([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'legacy-active-game',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $archivedId = DB::table('games')->insertGetId([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'legacy-archived-game',
            'is_active' => false,
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration->up();

        $videoGamesId = GameCategory::query()->where('slug', GameCategory::VIDEO_GAMES_SLUG)->value('id');
        $this->assertSame($videoGamesId, Game::withTrashed()->findOrFail($activeId)->game_category_id);
        $this->assertSame($videoGamesId, Game::withTrashed()->findOrFail($archivedId)->game_category_id);
    }

    public function test_game_creation_factory_and_seeder_require_and_assign_category(): void
    {
        // Direct DB insert without game_category_id must fail.
        $this->expectException(QueryException::class);
        Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'missing-category-game',
            'is_active' => true,
        ]);
    }

    public function test_game_factory_seeder_and_admin_form_require_category(): void
    {
        Storage::fake('public');

        $factoryGame = Game::factory()->create();
        $this->assertNotNull($factoryGame->game_category_id);
        $this->assertInstanceOf(GameCategory::class, $factoryGame->category);

        $this->seed(GamesTableSeeder::class);
        $videoGamesId = $this->defaultGameCategoryId();
        $this->assertSame(5, Game::query()->where('game_category_id', $videoGamesId)->count());

        $platform = Platform::query()->create(['name' => 'PC', 'slug' => 'pc-cat-test', 'is_active' => true]);
        $boardCategory = GameCategory::query()->where('slug', GameCategory::CARD_BOARD_GAMES_SLUG)->firstOrFail();

        Livewire::actingAs($this->admin)
            ->test(CmsAdmin::class, ['section' => 'games'])
            ->set('gameName', 'Tournament Chess')
            ->set('gameCategoryId', null)
            ->set('gamePlatformIds', [$platform->id])
            ->set('gameCardImage', UploadedFile::fake()->image('chess.png', 440, 330))
            ->call('saveGameTranslation')
            ->assertHasErrors(['gameCategoryId' => 'required']);

        Livewire::actingAs($this->admin)
            ->test(CmsAdmin::class, ['section' => 'games'])
            ->set('gameName', 'Tournament Chess')
            ->set('gameCategoryId', $boardCategory->id)
            ->set('gamePlatformIds', [$platform->id])
            ->set('gameCardImage', UploadedFile::fake()->image('chess.png', 440, 330))
            ->call('saveGameTranslation')
            ->assertHasNoErrors();

        $chess = Game::query()->where('slug', 'tournament-chess')->firstOrFail();
        $this->assertSame($boardCategory->id, $chess->game_category_id);
    }

    public function test_admin_can_create_rename_reorder_toggle_and_delete_categories_with_authorization(): void
    {
        $save = app(SaveGameCategoryAction::class);
        $toggle = app(ToggleGameCategoryStatusAction::class);
        $reorder = app(ReorderGameCategoryAction::class);
        $delete = app(DeleteGameCategoryAction::class);

        // Unauthorized staff/player cannot mutate categories.
        foreach ([$this->player, $this->supportAgent] as $unauthorized) {
            try {
                $save->execute($unauthorized, ['name' => 'Blocked']);
                $this->fail('Expected AuthorizationException for '.$unauthorized->email);
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }

        // Create
        $created = $save->execute($this->admin, [
            'name' => 'Tabletop Classics',
            'display_order' => 3,
            'is_active' => true,
        ]);
        $this->assertSame('tabletop-classics', $created->slug);

        // Rename
        $renamed = $save->execute($this->admin, [
            'name' => 'Strategy Classics',
            'slug' => 'strategy-classics',
            'display_order' => 3,
            'is_active' => true,
        ], $created);
        $this->assertSame('Strategy Classics', $renamed->name);
        $this->assertSame('strategy-classics', $renamed->slug);

        // Reorder up
        $reorder->execute($this->admin, $renamed, 'up');
        $orderedSlugs = GameCategory::query()->ordered()->pluck('slug')->all();
        $this->assertSame([GameCategory::VIDEO_GAMES_SLUG, 'strategy-classics', GameCategory::CARD_BOARD_GAMES_SLUG], $orderedSlugs);

        // Deactivate & activate
        $deactivated = $toggle->execute($this->admin, $renamed, false);
        $this->assertFalse($deactivated->is_active);
        $activated = $toggle->execute($this->admin, $deactivated, true);
        $this->assertTrue($activated->is_active);

        // Delete succeeds when unused
        $delete->execute($this->admin, $activated);
        $this->assertDatabaseMissing('game_categories', ['id' => $activated->id]);
    }

    public function test_category_deletion_is_blocked_when_active_or_archived_games_reference_it(): void
    {
        $delete = app(DeleteGameCategoryAction::class);
        $boardCategory = GameCategory::query()->where('slug', GameCategory::CARD_BOARD_GAMES_SLUG)->firstOrFail();

        $game = Game::factory()->forCategory($boardCategory)->create(['slug' => 'backgammon']);

        // Blocked by active game
        try {
            $delete->execute($this->admin, $boardCategory);
            $this->fail('Expected deletion to be blocked while an active game references the category.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }

        // Soft-delete (archive) the game: deletion must STILL be blocked!
        $game->delete();
        $this->assertTrue($game->fresh()->trashed());

        try {
            $delete->execute($this->admin, $boardCategory);
            $this->fail('Expected deletion to be blocked while an archived game references the category.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('archived games', $e->getMessage());
        }

        // Reassign archived game to Video Games, then deletion succeeds
        $game->update(['game_category_id' => $this->defaultGameCategoryId()]);
        $delete->execute($this->admin, $boardCategory);
        $this->assertDatabaseMissing('game_categories', ['id' => $boardCategory->id]);
    }

    public function test_admin_games_tabs_deactivation_confirmation_and_admin_v2_schedules_preserve_inactive_access(): void
    {
        $videoCategory = GameCategory::query()->where('slug', GameCategory::VIDEO_GAMES_SLUG)->firstOrFail();
        $boardCategory = GameCategory::query()->where('slug', GameCategory::CARD_BOARD_GAMES_SLUG)->firstOrFail();

        $valorant = Game::factory()->forCategory($videoCategory)->create(['slug' => 'valorant-admin']);
        $valorant->translations()->create(['locale' => 'en', 'name' => 'Valorant Admin']);

        $chess = Game::factory()->forCategory($boardCategory)->create(['slug' => 'chess-admin']);
        $chess->translations()->create(['locale' => 'en', 'name' => 'Chess Admin']);

        // Create a V2 schedule template for the board game
        $template = TournamentTemplate::query()->create([
            'uuid' => Str::uuid()->toString(),
            'workflow_version' => 2,
            'game_id' => $chess->id,
            'created_by' => $this->admin->id,
            'competition_type' => CompetitionType::TOURNAMENT,
            'name' => 'Daily Chess Masters',
            'format' => 'single_elimination',
            'max_participants' => 8,
            'min_participants' => 2,
            'entry_fee' => '0.00',
            'prize_model' => 'v2_percentage',
            'checkin_minutes' => 0,
            'is_recurring' => true,
            'recurrence_frequency' => 'daily',
            'timezone' => 'Europe/Amsterdam',
        ]);
        $slot = TournamentScheduleSlot::query()->create([
            'uuid' => Str::uuid()->toString(),
            'tournament_template_id' => $template->id,
            'identity_key' => 'd:18:00:00',
            'local_start_time' => '18:00:00',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'workflow_version' => 2,
            'template_id' => $template->id,
            'schedule_slot_id' => $slot->id,
            'occurrence_period_key' => '2026-11-24',
            'game_id' => $chess->id,
            'competition_type' => CompetitionType::TOURNAMENT,
            'name' => 'Daily Chess Masters - Nov 24, 2026 6:00 PM',
            'slug' => 'daily-chess-masters-1',
            'status' => TournamentStatus::REGISTRATION_OPEN,
            'max_participants' => 8,
            'min_participants' => 2,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'created_by' => $this->admin->id,
        ]);

        // Admin Games tabs filter games and deactivation opens confirmation first
        Livewire::actingAs($this->admin)
            ->test(CmsAdmin::class, ['section' => 'games'])
            ->assertSee('All Games')
            ->assertSee('Video Games')
            ->assertSee('Card & Board Games')
            ->assertSee('Valorant Admin')
            ->assertSee('Chess Admin')
            ->call('setGameCategoryFilter', GameCategory::CARD_BOARD_GAMES_SLUG)
            ->assertSee('Chess Admin')
            ->assertDontSee('Valorant Admin')
            ->call('requestCategoryStatusChange', $boardCategory->id)
            ->assertSet('confirmDeactivateCategoryId', $boardCategory->id)
            ->assertSee('Hides this category, its games, and its tournaments from player discovery')
            ->assertSee('Preserves accepted registrations, ongoing match access')
            ->call('confirmDeactivateCategory')
            ->assertSet('confirmDeactivateCategoryId', null)
            ->assertSee('Inactive')
            ->assertSee('Chess Admin');

        $this->assertFalse($boardCategory->fresh()->is_active);

        // Authorized admins still see the inactive category's V2 schedule
        $adminSchedules = app(AdminV2ScheduleQuery::class)
            ->query(CompetitionType::TOURNAMENT, 'active', [])
            ->get();
        $this->assertTrue($adminSchedules->contains('id', $template->id));
    }
}
