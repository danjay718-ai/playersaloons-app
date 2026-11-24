<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Livewire\Landing\LandingPage;
use App\Livewire\Match\MatchDetail;
use App\Livewire\Tournament\PlayerTournamentList;
use App\Livewire\Tournament\PublicTournamentList;
use App\Livewire\Tournament\TournamentDetail;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameCategory;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\SubmitV2MatchResultAction;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Actions\CancelRegistrationAction;
use App\Modules\Tournament\Actions\RegisterForTournamentAction;
use App\Modules\Tournament\Actions\RegisterForV2TournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\MatchOutcome;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use Database\Seeders\LandingPageSeeder;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class GameCategoryDiscoveryAndVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private GameCategory $videoCategory;

    private GameCategory $boardCategory;

    private Game $valorant;

    private Game $chess;

    private Game $poker;

    private Platform $pc;

    private Platform $mobile;

    private User $admin;

    private User $playerA;

    private User $playerB;

    private User $playerC;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LandingPageSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);

        $this->videoCategory = GameCategory::query()->where('slug', GameCategory::VIDEO_GAMES_SLUG)->firstOrFail();
        $this->boardCategory = GameCategory::query()->where('slug', GameCategory::CARD_BOARD_GAMES_SLUG)->firstOrFail();

        $this->pc = Platform::query()->create(['name' => 'PC', 'slug' => 'pc-disc', 'is_active' => true]);
        $this->mobile = Platform::query()->create(['name' => 'Mobile', 'slug' => 'mobile-disc', 'is_active' => true]);

        $this->valorant = Game::factory()->forCategory($this->videoCategory)->create(['slug' => 'valorant-disc']);
        $this->valorant->translations()->create(['locale' => 'en', 'name' => 'Valorant']);
        $this->valorant->platforms()->attach([$this->pc->id]);

        $this->chess = Game::factory()->forCategory($this->boardCategory)->create(['slug' => 'chess-disc']);
        $this->chess->translations()->create(['locale' => 'en', 'name' => 'Chess Blitz']);
        $this->chess->platforms()->attach([$this->pc->id, $this->mobile->id]);

        $this->poker = Game::factory()->forCategory($this->boardCategory)->create(['slug' => 'poker-disc']);
        $this->poker->translations()->create(['locale' => 'en', 'name' => 'Holdem Poker']);
        $this->poker->platforms()->attach([$this->mobile->id]);

        $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->admin->assignRole('ADMIN');

        $this->playerA = $this->createPlayerWithWallet('100.00');
        $this->playerB = $this->createPlayerWithWallet('100.00');
        $this->playerC = $this->createPlayerWithWallet('100.00');
    }

    private function createPlayerWithWallet(string $balance): User
    {
        $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $user->assignRole('PLAYER');
        Wallet::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['uuid' => Str::uuid()->toString(), 'cached_balance' => $balance, 'status' => 'active'],
        );

        return $user->fresh('wallet');
    }

    private function createTournament(Game $game, array $overrides = []): Tournament
    {
        $workflowVersion = (int) ($overrides['workflow_version'] ?? 1);
        $templateId = $overrides['template_id'] ?? null;
        $slotId = $overrides['schedule_slot_id'] ?? null;

        if ($workflowVersion === 2 && $templateId === null) {
            $template = TournamentTemplate::query()->create([
                'uuid' => Str::uuid()->toString(),
                'workflow_version' => 2,
                'game_id' => $game->id,
                'created_by' => $this->admin->id,
                'competition_type' => CompetitionType::TOURNAMENT,
                'name' => $overrides['name'] ?? 'V2 Template '.$game->slug,
                'format' => 'single_elimination',
                'max_participants' => 8,
                'min_participants' => 2,
                'entry_fee' => $overrides['entry_fee'] ?? '0.00',
                'prize_model' => 'v2_percentage',
                'checkin_minutes' => 0,
                'is_recurring' => true,
                'recurrence_frequency' => 'daily',
                'timezone' => 'Europe/Amsterdam',
            ]);
            $slot = TournamentScheduleSlot::query()->create([
                'uuid' => Str::uuid()->toString(),
                'tournament_template_id' => $template->id,
                'identity_key' => 'd:'.Str::random(6),
                'local_start_time' => '18:00:00',
                'is_active' => true,
                'sort_order' => 0,
            ]);
            $templateId = $template->id;
            $slotId = $slot->id;
        }

        return Tournament::query()->create(array_merge([
            'uuid' => Str::uuid()->toString(),
            'workflow_version' => $workflowVersion,
            'template_id' => $templateId,
            'schedule_slot_id' => $slotId,
            'occurrence_period_key' => $workflowVersion === 2 ? '2026-11-24-'.Str::random(4) : null,
            'game_id' => $game->id,
            'platform_id' => $this->pc->id,
            'platform_ids' => [$this->pc->id],
            'competition_type' => CompetitionType::TOURNAMENT,
            'name' => 'Open Cup '.Str::random(4),
            'slug' => 'open-cup-'.Str::lower(Str::random(8)),
            'status' => TournamentStatus::REGISTRATION_OPEN,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'team_size' => 1,
            'frequency' => 'daily',
            'start_at' => now()->addHours(2),
            'end_at' => now()->addHours(4),
            'join_closes_at' => now()->addHours(2),
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_homepage_category_tabs_search_and_filtered_tournament_links(): void
    {
        $this->createTournament($this->valorant, ['name' => 'Valorant Clash']);
        $this->createTournament($this->chess, ['name' => 'Chess Grandmaster Open']);

        Livewire::test(LandingPage::class)
            ->assertSee('All Games')
            ->assertSee('Video Games')
            ->assertSee('Card & Board Games')
            ->assertSee('Valorant')
            ->assertSee('Chess Blitz')
            ->assertSee('/tournaments?category='.GameCategory::CARD_BOARD_GAMES_SLUG.'&gameId='.$this->chess->id)
            ->call('selectCategory', GameCategory::CARD_BOARD_GAMES_SLUG)
            ->assertSee('Chess Blitz')
            ->assertSee('Holdem Poker')
            ->assertDontSee('Valorant')
            ->set('gameSearch', 'Chess')
            ->assertSee('Chess Blitz')
            ->assertDontSee('Holdem Poker');
    }

    public function test_legacy_and_v2_discovery_filter_categories_games_featured_and_reset_incompatible_selections(): void
    {
        foreach ([false, true] as $v2Enabled) {
            config(['features.tournament_v2.enabled' => $v2Enabled]);
            $version = $v2Enabled ? 2 : 1;

            $valCup = $this->createTournament($this->valorant, [
                'workflow_version' => $version,
                'name' => "Valorant Featured V{$version}",
                'is_featured' => true,
            ]);
            $chessCup = $this->createTournament($this->chess, [
                'workflow_version' => $version,
                'name' => "Chess Featured V{$version}",
                'is_featured' => true,
                'platform_id' => $this->pc->id,
                'platform_ids' => [$this->pc->id],
            ]);
            $pokerCup = $this->createTournament($this->poker, [
                'workflow_version' => $version,
                'name' => "Poker Night V{$version}",
                'is_featured' => false,
                'platform_id' => $this->mobile->id,
                'platform_ids' => [$this->mobile->id],
            ]);

            // Public tournament list
            Livewire::test(PublicTournamentList::class)
                ->assertSee($valCup->name)
                ->assertSee($chessCup->name)
                ->assertSee($pokerCup->name)
                // Select game in Video Games + compatible filters
                ->call('selectGame', (string) $this->valorant->id)
                ->set('platformId', (string) $this->pc->id)
                ->assertSee($valCup->name)
                ->assertDontSee($chessCup->name)
                // Switch to Card & Board Games: incompatible Valorant gameId must clear while platform filter stays!
                ->call('selectCategory', GameCategory::CARD_BOARD_GAMES_SLUG)
                ->assertSet('gameId', '')
                ->assertSet('platformId', (string) $this->pc->id)
                ->assertSee($chessCup->name)
                ->assertDontSee($valCup->name)
                ->assertDontSee($pokerCup->name) // excluded because platformId is PC
                // Clear platform filter, narrow by Poker game
                ->set('platformId', '')
                ->call('selectGame', (string) $this->poker->id)
                ->assertSee($pokerCup->name)
                ->assertDontSee($chessCup->name);

            // Logged-in player tournament list
            Livewire::actingAs($this->playerA)
                ->test(PlayerTournamentList::class)
                ->call('selectCategory', GameCategory::CARD_BOARD_GAMES_SLUG)
                ->assertSee($chessCup->name)
                ->assertDontSee($valCup->name)
                ->call('selectGame', (string) $this->chess->id)
                ->assertSee($chessCup->name)
                ->assertDontSee($pokerCup->name);
        }
    }

    public function test_inactive_category_hides_discovery_and_blocks_new_registrations_including_api(): void
    {
        config(['features.tournament_v2.enabled' => true]);

        $v1BoardCup = $this->createTournament($this->chess, [
            'workflow_version' => 1,
            'name' => 'Board V1 Open',
            'is_featured' => true,
        ]);
        $v2BoardCup = $this->createTournament($this->chess, [
            'workflow_version' => 2,
            'name' => 'Board V2 Open',
            'is_featured' => true,
        ]);
        $v2VideoCup = $this->createTournament($this->valorant, [
            'workflow_version' => 2,
            'name' => 'Video V2 Open',
            'is_featured' => true,
        ]);

        // Deactivate Card & Board Games
        $this->boardCategory->update(['is_active' => false]);

        // Homepage hides inactive category & games
        Livewire::test(LandingPage::class)
            ->assertDontSee('Card & Board Games')
            ->assertDontSee('Chess Blitz')
            ->assertSee('Valorant');

        // Public & Player tournament pages hide inactive category, game selector items, All Games, and featured results
        Livewire::test(PublicTournamentList::class)
            ->assertDontSee('Card & Board Games')
            ->assertDontSee('Chess Blitz')
            ->assertDontSee($v2BoardCup->name)
            ->assertSee($v2VideoCup->name);

        Livewire::actingAs($this->playerA)
            ->test(PlayerTournamentList::class)
            ->assertDontSee('Card & Board Games')
            ->assertDontSee('Chess Blitz')
            ->assertDontSee($v2BoardCup->name)
            ->assertSee($v2VideoCup->name);

        // Direct Game Hub returns 404 when category is inactive
        $this->get(route('games.show', $this->chess))->assertNotFound();

        // Public API hides tournaments in inactive category
        $this->getJson('/api/v1/tournaments')
            ->assertOk()
            ->assertJsonMissing(['uuid' => $v2BoardCup->uuid])
            ->assertJsonFragment(['uuid' => $v2VideoCup->uuid]);

        // Shared V1 registration action blocks new registration
        try {
            app(RegisterForTournamentAction::class)->execute($v1BoardCup, $this->playerA, null, 'PlayerA');
            $this->fail('Expected V1 registration to fail for inactive category.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('inactive', strtolower($e->getMessage()));
        }

        // Shared V2 registration action blocks new registration
        try {
            app(RegisterForV2TournamentAction::class)->execute($v2BoardCup, $this->playerA, null, 'PlayerA');
            $this->fail('Expected V2 registration to fail for inactive category.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('inactive', strtolower($e->getMessage()));
        }

        // API registration endpoint also blocks new registration (422)
        Sanctum::actingAs($this->playerA);
        $this->postJson("/api/v1/tournaments/{$v2BoardCup->uuid}/register", [
            'game_id_value' => 'PlayerA',
            'platform_id' => $this->pc->id,
        ])->assertStatus(422);
    }

    public function test_inactive_category_preserves_existing_registrations_cancellation_matches_advancement_and_payouts(): void
    {
        config(['features.tournament_v2.enabled' => true]);

        // 1. V1 tournament: player registers while active, category deactivated -> player keeps cancellation right & refund
        $v1Cup = $this->createTournament($this->chess, [
            'workflow_version' => 1,
            'name' => 'Cancellable Chess Cup',
            'entry_fee' => '10.00',
        ]);
        $v1Reg = app(RegisterForTournamentAction::class)->execute($v1Cup, $this->playerC, null, 'PlayerC', 'auto', null, $this->pc->id);
        $this->assertSame('90.00', $this->playerC->wallet->fresh()->cached_balance);

        // 2. V2 tournament: two players register while active, category deactivated -> ongoing match, completion, advancement, payout all continue!
        $v2Cup = $this->createTournament($this->chess, [
            'workflow_version' => 2,
            'name' => 'Ongoing Chess Finals',
            'entry_fee' => '10.00',
            'max_participants' => 2,
            'min_participants' => 2,
            'start_at' => now()->addMinutes(30),
            'join_closes_at' => now()->addMinutes(30),
        ]);

        $regA = app(RegisterForV2TournamentAction::class)->execute($v2Cup, $this->playerA, null, 'PlayerA', 'auto', null, $this->pc->id);
        $regB = app(RegisterForV2TournamentAction::class)->execute($v2Cup, $this->playerB, null, 'PlayerB', 'auto', null, $this->pc->id);

        $v2Cup->refresh();
        $this->assertSame(TournamentStatus::ONGOING, $v2Cup->status);
        $match = GameMatch::query()->where('tournament_id', $v2Cup->id)->firstOrFail();

        // Now deactivate the category while both tournaments exist!
        $this->boardCategory->update(['is_active' => false]);

        // Existing V1 player can still cancel and receive full refund
        app(CancelRegistrationAction::class)->execute($v1Reg, $this->playerC);
        $this->assertSame(RegistrationStatus::CANCELLED, $v1Reg->fresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, $v1Reg->fresh()->payment_status);
        $this->assertSame('100.00', $this->playerC->wallet->fresh()->cached_balance);

        // Accepted V2 participants can still view tournament history and match detail
        Livewire::actingAs($this->playerA)
            ->test(TournamentDetail::class, ['uuid' => $v2Cup->uuid])
            ->assertOk()
            ->assertSee($v2Cup->display_name);

        Livewire::actingAs($this->playerA)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertOk();

        // Players complete the match -> bracket advances -> tournament completes -> prize is paid out!
        $submitV2 = app(SubmitV2MatchResultAction::class);
        $submitV2->execute($match, $this->playerA->id, MatchOutcome::WIN);
        $submitV2->execute($match->fresh(), $this->playerB->id, MatchOutcome::LOSS);

        $match->refresh();
        $v2Cup->refresh();
        $this->assertSame(MatchStatus::COMPLETED, $match->status);
        $this->assertSame($regA->id, $match->winner_registration_id);
        $this->assertSame(TournamentStatus::COMPLETED, $v2Cup->status);
        $this->assertSame('paid', $v2Cup->payout_status);
        $this->assertGreaterThan(90.0, (float) $this->playerA->wallet->fresh()->cached_balance);
    }
}
