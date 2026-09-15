<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CmsAdmin;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\HeadToHeadRating;
use App\Modules\Operations\Services\AdminDeletionService;
use App\Modules\Operations\Services\GameDeletionService;
use App\Modules\Tournament\Models\Tournament;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DeletedGamesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('SUPER_ADMIN');
        $this->actingAs($this->admin);
        $this->game = Game::create(['uuid' => Str::uuid(), 'slug' => 'recoverable-game', 'is_active' => true]);
        $this->game->translations()->create(['locale' => 'en', 'name' => 'Recoverable Game']);
    }

    private function tournament(string $status = 'COMPLETED'): Tournament
    {
        return Tournament::create([
            'uuid' => Str::uuid(), 'game_id' => $this->game->id,
            'name' => 'Historical Cup', 'slug' => Str::uuid(), 'status' => $status,
            'max_participants' => 16, 'min_participants' => 2, 'entry_fee' => 0,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_deleted_games_are_separate_and_restore_as_disabled(): void
    {
        app(AdminDeletionService::class)->delete('games', [$this->game->id], $this->admin);

        Livewire::test(CmsAdmin::class, ['section' => 'games'])
            ->assertSee('Deleted Games')
            ->assertDontSee('Recoverable Game')
            ->call('setGameRecordTab', 'deleted')
            ->assertSee('Recoverable Game')
            ->assertSee('Restore')
            ->assertSee('Permanent Delete')
            ->call('restoreGame', $this->game->id)
            ->assertDontSee('Recoverable Game')
            ->call('setGameRecordTab', 'active')
            ->assertSee('Recoverable Game');

        $this->assertFalse($this->game->fresh()->is_active);
        $this->assertNull($this->game->fresh()->deleted_at);
    }

    public function test_unused_deleted_game_can_be_permanently_deleted_after_explicit_confirmation(): void
    {
        $platform = Platform::create(['name' => 'Console', 'slug' => 'console', 'is_active' => true]);
        $this->game->platforms()->attach($platform);
        app(AdminDeletionService::class)->delete('games', [$this->game->id], $this->admin);

        Livewire::test(CmsAdmin::class, ['section' => 'games'])
            ->call('confirmPermanentDeleteGame', $this->game->id)
            ->call('permanentlyDeleteGame')
            ->assertHasErrors('permanentDeleteConfirmation')
            ->set('permanentDeleteConfirmation', 'DELETE')
            ->call('permanentlyDeleteGame')
            ->assertHasNoErrors()
            ->assertSet('permanentDeleteGameId', null);

        $this->assertDatabaseMissing('games', ['id' => $this->game->id]);
        $this->assertDatabaseMissing('game_translations', ['game_id' => $this->game->id]);
        $this->assertDatabaseMissing('game_platform', ['game_id' => $this->game->id]);
        $this->assertDatabaseHas('platforms', ['id' => $platform->id]);
        $this->assertDatabaseHas('activity_log', ['description' => 'admin_game_permanently_deleted']);
    }

    public function test_permanent_deletion_preserves_connected_deleted_tournaments(): void
    {
        $tournament = $this->tournament();
        $tournament->delete();
        app(AdminDeletionService::class)->delete('games', [$this->game->id], $this->admin);

        Livewire::test(CmsAdmin::class, ['section' => 'games'])
            ->call('confirmPermanentDeleteGame', $this->game->id)
            ->assertSet('permanentDeleteReferences', ['tournaments' => 1])
            ->assertSee('Blocked: connected data must be preserved.')
            ->assertSee('These records still reference this game.')
            ->assertSee('Tournaments: 1 record(s)')
            ->set('permanentDeleteConfirmation', 'DELETE')
            ->call('permanentlyDeleteGame')
            ->assertHasErrors('permanentDeleteConfirmation');

        $this->assertSoftDeleted($this->game);
        $this->assertDatabaseHas('tournaments', ['id' => $tournament->id]);
        $this->assertSame('Recoverable Game', $tournament->fresh()->game->localizedName('en'));
    }

    public function test_permanent_delete_rechecks_dependencies_added_after_preview(): void
    {
        app(AdminDeletionService::class)->delete('games', [$this->game->id], $this->admin);
        $component = Livewire::test(CmsAdmin::class, ['section' => 'games'])
            ->call('confirmPermanentDeleteGame', $this->game->id)
            ->assertSet('permanentDeleteReferences', []);
        $this->tournament();
        $component->set('permanentDeleteConfirmation', 'DELETE')
            ->call('permanentlyDeleteGame')
            ->assertHasErrors('permanentDeleteConfirmation');

        $this->assertSoftDeleted($this->game);
    }

    public function test_active_tournament_blocks_soft_deletion(): void
    {
        $tournament = $this->tournament('ONGOING');
        try {
            app(AdminDeletionService::class)->delete('games', [$this->game->id], $this->admin);
            $this->fail('Active tournament should block soft deletion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('selectedIds', $exception->errors());
        }

        $this->assertNull($this->game->fresh()->deleted_at);
        $this->assertSame('ONGOING', $tournament->fresh()->status->value);
    }

    public function test_delete_permission_does_not_grant_restore_or_permanent_delete(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'GAME_CATALOG_STAFF', 'guard_name' => 'web']));
        $user->givePermissionTo(['games.view', 'games.delete']);
        app(AdminDeletionService::class)->delete('games', [$this->game->id], $this->admin);
        Livewire::actingAs($user)->test(CmsAdmin::class, ['section' => 'games'])
            ->call('restoreGame', $this->game->id)->assertStatus(403);
        Livewire::actingAs($user)->test(CmsAdmin::class, ['section' => 'games'])
            ->call('confirmPermanentDeleteGame', $this->game->id)->assertStatus(403);

        $this->assertSoftDeleted($this->game);
    }

    public function test_live_game_cannot_be_permanently_deleted(): void
    {
        $this->expectException(ModelNotFoundException::class);
        app(GameDeletionService::class)->permanentlyDelete($this->game->id, $this->admin);
    }

    public function test_permanent_deletion_blocks_cascading_rating_relationships(): void
    {
        $rating = HeadToHeadRating::create(['user_id' => $this->admin->id, 'game_id' => $this->game->id, 'rating' => 1300]);
        app(AdminDeletionService::class)->delete('games', [$this->game->id], $this->admin);

        Livewire::test(CmsAdmin::class, ['section' => 'games'])
            ->call('confirmPermanentDeleteGame', $this->game->id)
            ->assertSet('permanentDeleteReferences', ['head_to_head_ratings' => 1])
            ->set('permanentDeleteConfirmation', 'DELETE')
            ->call('permanentlyDeleteGame')
            ->assertHasErrors('permanentDeleteConfirmation');

        $this->assertDatabaseHas('head_to_head_ratings', ['id' => $rating->id, 'rating' => 1300]);
        $this->assertSoftDeleted($this->game);
    }

    public function test_cancel_permanent_deletion_clears_dialog_without_removing_game(): void
    {
        app(AdminDeletionService::class)->delete('games', [$this->game->id], $this->admin);
        Livewire::test(CmsAdmin::class, ['section' => 'games'])
            ->call('confirmPermanentDeleteGame', $this->game->id)
            ->set('permanentDeleteConfirmation', 'DELETE')
            ->call('cancelPermanentDeleteGame')
            ->assertSet('permanentDeleteGameId', null)
            ->assertSet('permanentDeleteConfirmation', '')
            ->assertSet('permanentDeleteReferences', []);

        $this->assertSoftDeleted($this->game);
    }

    public function test_permission_migration_grants_existing_super_admin_recovery_actions(): void
    {
        $role = Role::findByName('SUPER_ADMIN');
        $role->revokePermissionTo(['games.restore', 'games.force_delete']);
        $migration = require database_path('migrations/2026_09_15_000002_add_game_recovery_and_permanent_deletion_permissions.php');
        $migration->up();

        $this->assertTrue($role->fresh()->hasPermissionTo('games.restore'));
        $this->assertTrue($role->fresh()->hasPermissionTo('games.force_delete'));
    }
}
