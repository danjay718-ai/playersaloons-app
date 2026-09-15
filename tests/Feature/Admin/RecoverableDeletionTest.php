<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CmsAdmin;
use App\Livewire\Admin\RecoverableDelete;
use App\Livewire\Admin\RolePermissionAdmin;
use App\Livewire\Community\PlayerReviewPage;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Community\Models\Advertisement;
use App\Modules\Community\Models\NewsletterCampaign;
use App\Modules\Community\Models\PlayerReview;
use App\Modules\Compliance\Models\BlockedCountry;
use App\Modules\Compliance\Services\CountryEligibilityService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserGameAccount;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Operations\Services\AdminDeletionService;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Modules\Tournament\Support\CompetitionPlatforms;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RecoverableDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('SUPER_ADMIN');
        $this->actingAs($this->superAdmin);
        $this->game = Game::create(['uuid' => Str::uuid(), 'slug' => 'preserved-game', 'is_active' => true]);
        $this->game->translations()->create(['locale' => 'en', 'name' => 'Preserved Game']);
    }

    private function tournament(string $status = 'DRAFT'): Tournament
    {
        return Tournament::create([
            'uuid' => Str::uuid(), 'game_id' => $this->game->id, 'name' => 'Preserved Cup',
            'slug' => Str::uuid(), 'status' => $status, 'max_participants' => 16,
            'min_participants' => 2, 'entry_fee' => 0, 'created_by' => $this->superAdmin->id,
        ]);
    }

    private function deleteRecords(string $resource, array $ids, ?int $parentId = null): void
    {
        app(AdminDeletionService::class)->delete($resource, $ids, $this->superAdmin, $parentId);
    }

    public function test_deleting_game_preserves_tournaments_accounts_streams_and_historical_names(): void
    {
        $tournament = $this->tournament('COMPLETED');
        $platform = Platform::create(['name' => 'Test Platform', 'slug' => 'test-platform', 'is_active' => true]);
        $account = UserGameAccount::create(['user_id' => $this->superAdmin->id, 'game_id' => $this->game->id, 'platform_id' => $platform->id, 'game_id_value' => 'Player123']);
        $stream = StreamChannel::create(['provider' => 'youtube', 'source_url' => 'https://www.youtube.com/watch?v=test', 'game_id' => $this->game->id, 'title' => 'Preserved stream']);

        $this->deleteRecords('games', [$this->game->id]);

        $this->assertSoftDeleted($this->game);
        $this->assertNull(Game::find($this->game->id));
        $this->assertSame('Preserved Game', $tournament->fresh()->game->localizedName('en'));
        $this->assertSame($this->game->id, $account->fresh()->game->id);
        $this->assertSame($this->game->id, $stream->fresh()->game->id);
        $this->assertDatabaseCount('tournaments', 1);
        $this->assertDatabaseCount('user_game_accounts', 1);
        $this->assertDatabaseCount('stream_channels', 1);

        Livewire::test(CmsAdmin::class, ['section' => 'games'])->set('gameRecordTab', 'archived')->assertDontSee('Preserved Game')->assertDontSee('Archived Games');
    }

    public function test_deleted_platform_remains_readable_in_tournament_history(): void
    {
        $platform = Platform::create(['name' => 'Historical Platform', 'slug' => 'historical-platform', 'is_active' => true]);
        $tournament = $this->tournament('COMPLETED');
        $tournament->update(['platform_id' => $platform->id, 'platform_ids' => [$platform->id]]);

        $this->deleteRecords('platforms', [$platform->id]);

        $this->assertNull(Platform::find($platform->id));
        $this->assertSame('Historical Platform', $tournament->fresh()->platform->name);
        $this->assertSame('Historical Platform', $tournament->fresh()->platform_names);
    }

    public function test_deleting_user_preserves_wallet_and_immutable_ledger_with_owner(): void
    {
        $user = User::factory()->create();
        $user->assignRole('PLAYER');
        $wallet = Wallet::create(['uuid' => Str::uuid(), 'user_id' => $user->id, 'cached_balance' => 25, 'status' => 'active']);
        $entry = LedgerEntry::create(['uuid' => Str::uuid(), 'wallet_id' => $wallet->id, 'idempotency_key' => Str::uuid(), 'type' => 'DEPOSIT', 'amount' => 25, 'running_balance' => 25, 'created_at' => now()]);

        $this->deleteRecords('users', [$user->id]);

        $this->assertSoftDeleted($user);
        $this->assertDatabaseHas('ledger_entries', ['id' => $entry->id, 'amount' => 25]);
        $this->assertSame($user->id, $wallet->fresh()->user->id);
        $this->assertSame('25.00', $wallet->fresh()->cached_balance);
    }

    public function test_historical_match_resolves_soft_deleted_tournament(): void
    {
        $tournament = $this->tournament();
        $bracket = Bracket::create(['tournament_id' => $tournament->id, 'generated_at' => now()]);
        $round = Round::create(['bracket_id' => $bracket->id, 'round_number' => 1]);
        $match = GameMatch::create(['uuid' => Str::uuid(), 'tournament_id' => $tournament->id, 'round_id' => $round->id, 'status' => 'completed']);

        $this->deleteRecords('tournaments', [$tournament->id]);

        $this->assertDatabaseHas('matches', ['id' => $match->id]);
        $this->assertSame('Preserved Cup', $match->fresh()->tournament->name);
        $this->assertNull(Tournament::find($tournament->id));
    }

    public function test_bulk_delete_rolls_back_entire_selection_when_a_tournament_is_active(): void
    {
        $draft = $this->tournament();
        $active = $this->tournament('ONGOING');
        try {
            $this->deleteRecords('tournaments', [$draft->id, $active->id]);
            $this->fail('Active tournament deletion must be blocked.');
        } catch (ValidationException) {
            $this->assertNotNull($draft->fresh());
            $this->assertNull($draft->fresh()->deleted_at);
            $this->assertNull($active->fresh()->deleted_at);
        }
    }

    public function test_super_admin_has_explicit_delete_permissions_on_roles_page(): void
    {
        $role = Role::findByName('SUPER_ADMIN');
        foreach (array_unique(array_column(AdminDeletionService::RESOURCES, 1)) as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission), $permission);
        }
        Livewire::test(RolePermissionAdmin::class)->assertSee('games.delete')->assertSee('tournaments.delete');
    }

    public function test_manage_permission_does_not_authorize_delete(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('ADMIN');

        Livewire::actingAs($admin)->test(RecoverableDelete::class, ['resource' => 'games'])->assertDontSee('Delete games')->call('open')->assertForbidden();
    }

    public function test_delete_permission_can_be_delegated_to_staff(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('ADMIN');
        $admin->givePermissionTo('games.delete');

        Livewire::actingAs($admin)->test(RecoverableDelete::class, ['resource' => 'games', 'recordId' => $this->game->id])->call('open')->assertSee('future generation')->call('deleteSelected')->assertDispatched('admin-records-deleted');
        $this->assertSoftDeleted($this->game);
    }

    public function test_bulk_selection_requires_confirmation_and_preserves_deleted_records(): void
    {
        $first = Advertisement::create(['uuid' => Str::uuid(), 'title' => 'First', 'is_active' => true, 'created_by' => $this->superAdmin->id]);
        $second = Advertisement::create(['uuid' => Str::uuid(), 'title' => 'Second', 'is_active' => true, 'created_by' => $this->superAdmin->id]);

        Livewire::test(RecoverableDelete::class, ['resource' => 'advertisements'])->call('open')->set('selectedIds', [$first->id, $second->id])->call('preview')->assertSee('impression')->call('deleteSelected')->assertDispatched('admin-records-deleted');

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
        $this->assertSame(0, Advertisement::count());
        $this->assertSame(2, Advertisement::withTrashed()->count());
    }

    public function test_direct_delete_without_review_is_rejected(): void
    {
        Livewire::test(RecoverableDelete::class, ['resource' => 'games'])->set('selectedIds', [$this->game->id])->call('deleteSelected')->assertStatus(422);
        $this->assertNotNull($this->game->fresh());
    }

    public function test_sending_campaign_is_protected(): void
    {
        $campaign = NewsletterCampaign::create(['uuid' => Str::uuid(), 'subject' => 'Sending', 'content' => 'Newsletter', 'status' => 'sending', 'created_by' => $this->superAdmin->id]);
        Livewire::test(RecoverableDelete::class, ['resource' => 'campaigns', 'recordId' => $campaign->id])->call('open')->assertSee('Blocked:')->call('deleteSelected')->assertHasErrors('selectedIds');
        $this->assertNull($campaign->fresh()->deleted_at);
    }

    public function test_deleted_stream_keeps_chat_and_viewer_relationships(): void
    {
        $stream = StreamChannel::create(['provider' => 'youtube', 'source_url' => 'https://youtube.com/watch?v=test', 'title' => 'Deleted stream', 'is_live' => true, 'is_public' => true]);
        $message = $stream->chatMessages()->create(['user_id' => $this->superAdmin->id, 'message' => 'Preserved chat']);

        $this->deleteRecords('streams', [$stream->id]);

        $this->assertSoftDeleted($stream);
        $this->assertSame($stream->id, $message->fresh()->streamChannel->id);
        $this->assertDatabaseCount('stream_chat_messages', 1);
    }

    public function test_country_deletion_invalidates_cached_blocking_rule(): void
    {
        $country = BlockedCountry::create(['country_code' => 'US', 'country_name' => 'United States', 'message' => 'Blocked']);
        $eligibility = app(CountryEligibilityService::class);
        $this->assertContains('US', $eligibility->blockedCodes());
        $this->deleteRecords('countries', [$country->id]);
        $this->assertNotContains('US', $eligibility->blockedCodes());
        $this->assertSoftDeleted($country);
    }

    public function test_player_can_submit_new_review_without_overwriting_deleted_history(): void
    {
        $player = User::factory()->create();
        $player->assignRole('PLAYER');
        $old = PlayerReview::create(['uuid' => Str::uuid(), 'user_id' => $player->id, 'rating' => 4, 'review' => 'Historical review', 'status' => 'approved']);
        $this->deleteRecords('reviews', [$old->id]);
        Livewire::actingAs($player)->test(PlayerReviewPage::class)->set('rating', 5)->set('review', 'My updated review for moderation')->call('submit')->assertHasNoErrors();
        $this->assertSame('Historical review', PlayerReview::withTrashed()->find($old->id)->review);
        $this->assertSame(2, PlayerReview::withTrashed()->where('user_id', $player->id)->count());
    }

    public function test_deleted_schedule_is_hidden_but_occurrence_keeps_parent_reference(): void
    {
        $template = TournamentTemplate::create(['uuid' => Str::uuid(), 'game_id' => $this->game->id, 'name' => 'Historical schedule', 'competition_type' => 'tournament', 'format' => 'single_elimination', 'max_participants' => 16, 'min_participants' => 2, 'entry_fee' => 0, 'is_recurring' => true, 'recurrence_frequency' => 'daily', 'timezone' => 'UTC']);
        $tournament = $this->tournament('COMPLETED');
        $tournament->update(['template_id' => $template->id]);
        $this->deleteRecords('tournament_schedules', [$template->id]);
        $this->assertNull(TournamentTemplate::find($template->id));
        $this->assertSame('Historical schedule', $tournament->fresh()->template->name);
    }

    public function test_deleted_slot_remains_readable_from_its_completed_occurrence(): void
    {
        $template = TournamentTemplate::create(['uuid' => Str::uuid(), 'game_id' => $this->game->id, 'name' => 'Slot parent', 'competition_type' => 'tournament', 'format' => 'single_elimination', 'max_participants' => 16, 'min_participants' => 2, 'entry_fee' => 0, 'timezone' => 'UTC']);
        $slot = TournamentScheduleSlot::create(['uuid' => Str::uuid(), 'tournament_template_id' => $template->id, 'identity_key' => 'morning', 'label' => 'Morning slot', 'local_start_time' => '09:00:00', 'is_active' => true]);
        $tournament = $this->tournament('COMPLETED');
        $tournament->update(['template_id' => $template->id, 'schedule_slot_id' => $slot->id]);

        $this->deleteRecords('schedule_slots', [$slot->id], $template->id);

        $this->assertSoftDeleted($slot);
        $this->assertSame('Morning slot', $tournament->fresh()->scheduleSlot->label);
        $this->assertSame(0, $template->fresh()->scheduleSlots()->count());
    }

    public function test_migration_grants_permissions_to_existing_super_admin_role(): void
    {
        $role = Role::findByName('SUPER_ADMIN');
        $role->revokePermissionTo('games.delete');
        $migration = require database_path('migrations/2026_09_15_000001_add_recoverable_admin_deletion.php');
        $migration->down();
        $migration->up();

        $this->assertTrue($role->fresh()->hasPermissionTo('games.delete'));
    }

    public function test_rollback_cannot_resurrect_deleted_records(): void
    {
        $this->deleteRecords('games', [$this->game->id]);
        $review = PlayerReview::create(['uuid' => Str::uuid(), 'user_id' => $this->superAdmin->id, 'rating' => 5, 'review' => 'Retained review', 'status' => 'approved']);
        $this->deleteRecords('reviews', [$review->id]);
        $migration = require database_path('migrations/2026_09_15_000001_add_recoverable_admin_deletion.php');

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_game_deletion_is_blocked_while_competition_is_active(): void
    {
        $this->tournament('ONGOING');
        Livewire::test(RecoverableDelete::class, ['resource' => 'games', 'recordId' => $this->game->id])->call('open')->assertSee('Blocked:')->call('deleteSelected')->assertHasErrors('selectedIds');
        $this->assertNull($this->game->fresh()->deleted_at);
    }

    public function test_platform_deletion_is_blocked_when_active_competition_uses_its_snapshot(): void
    {
        $platform = Platform::create(['name' => 'Active platform', 'slug' => 'active-platform', 'is_active' => true]);
        $tournament = $this->tournament('REGISTRATION_OPEN');
        $tournament->update(['platform_ids' => [$platform->id]]);
        Livewire::test(RecoverableDelete::class, ['resource' => 'platforms', 'recordId' => $platform->id])->call('open')->call('deleteSelected')->assertHasErrors('selectedIds');
        $this->assertNull($platform->fresh()->deleted_at);
    }

    public function test_draft_cannot_be_published_with_deleted_game(): void
    {
        $draft = $this->tournament();
        $this->deleteRecords('games', [$this->game->id]);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('available game');
        app(TournamentStateMachine::class)->guardCanPublish($draft);
    }

    public function test_draft_cannot_be_published_with_deleted_platform(): void
    {
        $platform = Platform::create(['name' => 'Deleted platform', 'slug' => 'deleted-platform', 'is_active' => true]);
        $draft = $this->tournament();
        $draft->update(['platform_ids' => [$platform->id]]);
        $this->deleteRecords('platforms', [$platform->id]);
        $this->assertTrue(CompetitionPlatforms::hasDeleted(['platform_ids' => [$platform->id]]));
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('available platforms');
        app(TournamentStateMachine::class)->guardCanPublish($draft);
    }
}
