<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Livewire\Match\MatchDetail;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameHeadToHeadDefault;
use App\Modules\CMS\Models\GameTournamentDefault;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\PlayerProgression;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\ResolveDisputeAction;
use App\Modules\Match\Actions\ResolveV2ResultTimeoutAction;
use App\Modules\Match\Actions\SubmitV2MatchResultAction;
use App\Modules\Match\Jobs\ResolveV2ResultTimeoutJob;
use App\Modules\Match\Jobs\ResolveV2ResultTimeoutsJob;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Services\V2StalledMatchService;
use App\Modules\Tournament\Actions\AwardV2PrizesAction;
use App\Modules\Tournament\Actions\CreateV2TournamentTemplateAction;
use App\Modules\Tournament\Actions\MaterializeV2OccurrenceAction;
use App\Modules\Tournament\Actions\PurgeEmptyV2OccurrencesAction;
use App\Modules\Tournament\Actions\RegisterForV2TournamentAction;
use App\Modules\Tournament\Actions\RequestV2CancellationAction;
use App\Modules\Tournament\Actions\ResetTournamentTestingDataAction;
use App\Modules\Tournament\Actions\VoteOnV2CancellationAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\V2TournamentDiscoveryService;
use App\Modules\Tournament\Services\V2TournamentLifecycle;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\MatchOutcome;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\WalletStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

final class TournamentV2WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Game $game;

    private Platform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('features.tournament_v2.enabled', true);
        $this->seed([RolesAndPermissionsSeeder::class, PlatformSystemUserSeeder::class, SystemSettingsSeeder::class]);
        $this->admin = $this->user('v2-admin@example.com', 'v2admin', 'SUPER_ADMIN');
        $this->game = Game::query()->create(['uuid' => Str::uuid(), 'slug' => 'v2-game', 'is_active' => true]);
        $this->platform = Platform::query()->create(['name' => 'PC', 'slug' => 'pc-v2', 'is_active' => true]);
        $this->game->platforms()->attach($this->platform);
        GameTournamentDefault::query()->create([
            'game_id' => $this->game->id,
            'default_platform_id' => $this->platform->id,
            'tournament_banner_path' => 'games/v2/tournament.webp',
            'description' => 'Default tournament copy',
            'rules' => 'Default tournament rules',
        ]);
        GameHeadToHeadDefault::query()->create([
            'game_id' => $this->game->id,
            'default_platform_id' => $this->platform->id,
            'head_to_head_banner_path' => 'games/v2/head-to-head.webp',
            'description' => 'Default H2H copy',
            'rules' => 'Default H2H rules',
        ]);
    }

    public function test_materialization_is_idempotent_and_snapshots_game_defaults(): void
    {
        $now = CarbonImmutable::parse('2026-08-28 10:00:00', 'UTC');
        $template = $this->template('daily', 4, '12:00');
        $slot = $template->scheduleSlots->firstOrFail();

        $first = app(MaterializeV2OccurrenceAction::class)->execute($slot, $this->admin, $now);
        $second = app(MaterializeV2OccurrenceAction::class)->execute($slot, $this->admin, $now);

        self::assertSame($first->id, $second->id);
        self::assertSame(2, $first->min_participants);
        self::assertSame(1, $first->team_size);
        self::assertSame(5, $first->waiting_result_time);
        self::assertSame('Default tournament copy', $first->description);
        self::assertSame('/storage/games/v2/tournament.webp', $first->banner_url);
        self::assertSame($this->platform->id, $first->platform_id);
        self::assertTrue($first->registration_close_at->equalTo($first->start_at));
        self::assertTrue($first->join_closes_at->equalTo($first->start_at));
    }

    public function test_local_testing_reset_removes_tournament_records_and_rebuilds_linked_wallet_and_xp_data(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        $player = $this->user('reset-player@example.com', 'resetplayer', 'PLAYER');
        $wallet = $this->walletFor($player, '9.00');

        $ledgerId = DB::table('ledger_entries')->insertGetId([
            'uuid' => (string) Str::uuid(), 'wallet_id' => $wallet->id,
            'reference_type' => Tournament::class, 'reference_id' => $tournament->id,
            'type' => 'entry_fee', 'amount' => '-1.00', 'running_balance' => '9.00',
            'description' => 'Tournament test entry', 'created_at' => now(),
        ]);
        DB::table('wallet_transactions')->insert([
            'uuid' => (string) Str::uuid(), 'wallet_id' => $wallet->id, 'ledger_entry_id' => $ledgerId,
            'type' => 'entry_fee', 'status' => 'completed', 'amount' => '-1.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('player_experience_awards')->insert([
            'uuid' => (string) Str::uuid(), 'user_id' => $player->id, 'source_type' => 'tournament', 'source_id' => $tournament->id,
            'reason' => 'participation', 'amount' => 10, 'metadata' => json_encode(['version' => 2]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        PlayerProgression::query()->create(['user_id' => $player->id, 'experience_points' => 10, 'level' => 1, 'tournaments_completed' => 1]);

        $summary = app(ResetTournamentTestingDataAction::class)->execute();

        self::assertSame(1, $summary['tournaments']);
        $this->assertDatabaseCount('tournaments', 0);
        $this->assertDatabaseCount('tournament_templates', 0);
        $this->assertDatabaseMissing('ledger_entries', ['id' => $ledgerId]);
        $this->assertDatabaseMissing('wallet_transactions', ['ledger_entry_id' => $ledgerId]);
        $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'cached_balance' => '0.00']);
        $this->assertDatabaseHas('player_progressions', ['user_id' => $player->id, 'experience_points' => 0, 'tournaments_completed' => 0]);
    }

    public function test_platform_head_to_head_template_is_fixed_to_one_versus_one_and_snapshots_the_type(): void
    {
        $template = app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'PC Evening H2H',
            'competition_type' => CompetitionType::HEAD_TO_HEAD->value,
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'max_teams' => 2,
            'entry_fee' => '1.00',
            'full_first_bps' => 9000,
            'full_second_bps' => 0,
            'slots' => [[
                'label' => 'Evening',
                'local_start_time' => '23:59',
                'schedule_start_at' => CarbonImmutable::parse('2026-08-29 23:59:00', 'UTC'),
                'schedule_end_at' => CarbonImmutable::parse('2026-08-29 23:59:59', 'UTC'),
            ]],
        ]);

        self::assertSame(CompetitionType::HEAD_TO_HEAD, $template->competition_type);
        self::assertSame(2, $template->max_participants);
        self::assertSame(2, $template->min_participants);

        $occurrence = app(MaterializeV2OccurrenceAction::class)->execute(
            $template->scheduleSlots->firstOrFail(),
            $this->admin,
            CarbonImmutable::parse('2026-08-29 10:00:00', 'UTC'),
        );

        self::assertNotNull($occurrence);
        self::assertSame(CompetitionType::HEAD_TO_HEAD, $occurrence->competition_type);
        self::assertSame(2, $occurrence->max_participants);
        self::assertSame(2, $occurrence->min_participants);
        self::assertSame(1, $occurrence->team_size);
        self::assertSame('Default H2H copy', $occurrence->description);
        self::assertSame('Default H2H rules', $occurrence->rules);
        self::assertSame('/storage/games/v2/head-to-head.webp', $occurrence->banner_url);
    }

    public function test_admin_can_manage_a_separate_game_head_to_head_template(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.games.head-to-head-defaults.edit', $this->game))
            ->assertOk()
            ->assertSee('Head-to-Head');

        $this->actingAs($this->admin)
            ->put(route('admin.games.head-to-head-defaults.update', $this->game), [
                'default_platform_id' => $this->platform->id,
                'description' => '<p>Changed H2H defaults.</p>',
                'rules' => '<p>H2H only rules.</p>',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('game_head_to_head_defaults', [
            'game_id' => $this->game->id,
            'description' => '<p>Changed H2H defaults.</p>',
            'rules' => '<p>H2H only rules.</p>',
        ]);
        $this->assertDatabaseHas('game_tournament_defaults', [
            'game_id' => $this->game->id,
            'description' => 'Default tournament copy',
        ]);
    }

    public function test_platform_head_to_head_has_separate_public_and_admin_management_pages(): void
    {
        $this->get('/h2h')
            ->assertOk()
            ->assertSee('Head-to-Head');

        $this->actingAs($this->admin)
            ->get('/admin/head-to-head')
            ->assertOk()
            ->assertSee('Head-to-Head schedules');

        $this->actingAs($this->admin)
            ->get('/admin/head-to-head/create')
            ->assertOk()
            ->assertSee('Create Head-to-Head schedule')
            ->assertSee('2 players (1v1)');
    }

    public function test_admin_head_to_head_management_supports_tournament_style_tabs_and_filters(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.h2h.index', [
                'status_tab' => 'all',
                'tab' => 'daily',
                'status' => TournamentStatus::REGISTRATION_OPEN->value,
                'game_id' => $this->game->id,
                'platform_id' => $this->platform->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
                'per_page' => 25,
            ]))
            ->assertOk()
            ->assertSee('Filter Head-to-Head')
            ->assertSee('Scheduled → Ongoing')
            ->assertSee('All platforms');
    }

    public function test_public_head_to_head_navigation_keeps_a_signed_in_player_in_the_public_shell(): void
    {
        $player = $this->user('public-h2h-player@example.com', 'publich2hplayer', 'PLAYER');

        $this->actingAs($player)
            ->get(route('platform-h2h', ['view' => 'guest']))
            ->assertOk()
            ->assertSee('id="public-nav"', false)
            ->assertDontSee('id="desktop-sidebar"', false);

        $this->actingAs($player)
            ->get(route('platform-h2h'))
            ->assertOk()
            ->assertSee('id="desktop-sidebar"', false);

        $this->actingAs($player)
            ->get(route('games.show', [
                'game' => $this->game,
                'competitionType' => 'head_to_head',
                'view' => 'guest',
            ]))
            ->assertOk()
            ->assertSee('id="public-nav"', false)
            ->assertDontSee('id="desktop-sidebar"', false);
    }

    public function test_admin_h2h_creation_forces_the_parent_to_two_players(): void
    {
        $start = now('UTC')->addDay()->setTime(18, 0);
        $end = $start->copy()->endOfDay();

        $this->actingAs($this->admin)
            ->post('/admin/head-to-head', [
                'competition_type' => 'head_to_head',
                'game_id' => $this->game->id,
                'platform_id' => $this->platform->id,
                'name' => 'Admin-created H2H',
                'description' => '<p>Dedicated 1v1 rules.</p>',
                'rules' => '<p>Report results after each match.</p>',
                'frequency' => 'daily',
                // The browser does not allow this in the dedicated UI. The
                // request test proves the server still persists exactly two.
                'max_teams' => 64,
                'entry_fee' => '1.00',
                'winning_points' => 15,
                'waiting_result_time' => 5,
                'full_first_percent' => 90,
                'full_second_percent' => 0,
                'slots' => [[
                    'label' => 'Evening',
                    'local_start_time' => '18:00',
                    'schedule_start_at' => $start->format('Y-m-d H:i:s'),
                    'schedule_end_at' => $end->format('Y-m-d H:i:s'),
                ]],
            ])
            ->assertRedirect(route('admin.h2h.index'));

        $this->assertDatabaseHas('tournament_templates', [
            'name' => 'Admin-created H2H',
            'competition_type' => CompetitionType::HEAD_TO_HEAD->value,
            'max_participants' => 2,
            'min_participants' => 2,
        ]);
    }

    public function test_admin_can_update_or_cancel_a_future_empty_slot_from_schedule_management(): void
    {
        $now = CarbonImmutable::now('UTC');
        $template = $this->template('daily', 4, $now->addHours(3)->format('H:i'));
        $occurrence = app(MaterializeV2OccurrenceAction::class)->execute(
            $template->scheduleSlots->firstOrFail(),
            $this->admin,
            $now,
        );
        self::assertNotNull($occurrence);

        $rescheduledStart = $now->addHours(4)->second(0);
        $this->actingAs($this->admin)
            ->put(route('admin.tournaments.v2.occurrences.update', $occurrence), [
                'name' => 'Updated empty slot',
                'platform_id' => $this->platform->id,
                'max_teams' => 4,
                'entry_fee' => '1.00',
                'start_at' => $rescheduledStart->format('Y-m-d H:i:s'),
                'end_date' => $rescheduledStart->addDay()->format('Y-m-d'),
                'waiting_result_time' => 5,
                'winning_points' => 15,
            ])
            ->assertRedirect(route('admin.tournaments.v2.templates.slots', $template));

        $this->assertDatabaseHas('tournaments', ['id' => $occurrence->id, 'name' => 'Updated empty slot']);

        $this->actingAs($this->admin)
            ->post(route('admin.tournaments.v2.occurrences.cancel', $occurrence))
            ->assertRedirect(route('admin.tournaments.v2.templates.slots', $template));

        $this->assertDatabaseHas('tournaments', ['id' => $occurrence->id, 'status' => TournamentStatus::CANCELLED->value]);
        $this->assertDatabaseHas('tournament_cancellations', ['tournament_id' => $occurrence->id, 'reason' => 'admin_slot_cancelled']);
    }

    public function test_elapsed_slots_wait_for_the_next_period_instead_of_precreating_it(): void
    {
        $daily = $this->template('daily', 4, '10:00');
        $dailyOccurrence = app(MaterializeV2OccurrenceAction::class)->execute(
            $daily->scheduleSlots->firstOrFail(),
            $this->admin,
            CarbonImmutable::parse('2026-08-26 11:00:00', 'UTC'),
        );
        self::assertNull($dailyOccurrence);

        $weekly = $this->template('weekly', 4, '10:00', ['day_of_week' => 0]);
        $weeklyOccurrence = app(MaterializeV2OccurrenceAction::class)->execute(
            $weekly->scheduleSlots->firstOrFail(),
            $this->admin,
            CarbonImmutable::parse('2026-08-26 11:00:00', 'UTC'),
        );
        self::assertNull($weeklyOccurrence);

        $monthly = $this->template('monthly', 4, '10:00', ['day_of_month' => 10]);
        $monthlyOccurrence = app(MaterializeV2OccurrenceAction::class)->execute(
            $monthly->scheduleSlots->firstOrFail(),
            $this->admin,
            CarbonImmutable::parse('2026-08-26 11:00:00', 'UTC'),
        );
        self::assertNull($monthlyOccurrence);
    }

    public function test_recurring_slot_created_after_todays_time_waits_until_the_next_day_begins(): void
    {
        $template = app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'Late Created Daily Tournament',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'max_teams' => 4,
            'entry_fee' => '0.00',
            'full_first_bps' => 9000,
            'full_second_bps' => 0,
            'slots' => [[
                'label' => 'Morning',
                'local_start_time' => '06:00',
                'schedule_start_at' => CarbonImmutable::parse('2026-08-29 06:00:00', 'UTC'),
                'schedule_end_at' => CarbonImmutable::parse('2026-08-29 23:59:00', 'UTC'),
            ]],
        ]);

        $skipped = app(MaterializeV2OccurrenceAction::class)->execute(
            $template->scheduleSlots->firstOrFail(),
            $this->admin,
            CarbonImmutable::parse('2026-08-29 21:00:00', 'UTC'),
        );
        self::assertNull($skipped);

        $occurrence = app(MaterializeV2OccurrenceAction::class)->execute(
            $template->scheduleSlots->firstOrFail(),
            $this->admin,
            CarbonImmutable::parse('2026-08-30 00:00:00', 'UTC'),
        );

        self::assertNotNull($occurrence);
        self::assertSame('2026-08-30 06:00:00', $occurrence->start_at->utc()->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-30 23:59:00', $occurrence->end_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_one_time_slot_materializes_once_with_its_exact_schedule_window(): void
    {
        $template = app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'One Time V2 Tournament',
            'frequency' => 'one_time',
            'timezone' => 'UTC',
            'max_teams' => 4,
            'entry_fee' => '0.00',
            'full_first_bps' => 9000,
            'full_second_bps' => 0,
            'slots' => [[
                'label' => 'Launch',
                'local_start_time' => '18:00',
                'schedule_start_at' => CarbonImmutable::parse('2026-09-02 18:00:00', 'UTC'),
                'schedule_end_at' => CarbonImmutable::parse('2026-09-02 23:00:00', 'UTC'),
            ]],
        ]);

        $slot = $template->scheduleSlots->firstOrFail();
        $first = app(MaterializeV2OccurrenceAction::class)->execute($slot, $this->admin);
        $second = app(MaterializeV2OccurrenceAction::class)->execute($slot, $this->admin);

        self::assertFalse($template->is_recurring);
        self::assertNull($template->recurrence_frequency);
        self::assertSame($first->id, $second->id);
        self::assertSame('one_time', $first->frequency);
        self::assertSame('2026-09-02 18:00:00', $first->start_at->utc()->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-02 23:00:00', $first->end_at->utc()->format('Y-m-d H:i:s'));
    }

    public function test_only_expired_empty_cancelled_occurrences_are_purged(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $empty = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        Tournament::query()->whereKey($empty->id)->update([
            'status' => TournamentStatus::CANCELLED->value,
            'end_at' => now()->subDays(31),
        ]);

        self::assertSame(1, app(PurgeEmptyV2OccurrencesAction::class)->execute());
        self::assertNull(Tournament::withTrashed()->find($empty->id));
    }

    public function test_underfilled_tournament_starts_with_two_and_generates_visible_bye(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $register = app(RegisterForV2TournamentAction::class);
        $register->execute($tournament, $this->user('p1-v2@example.com', 'p1v2', 'PLAYER'), null, 'p1');
        $register->execute($tournament->fresh(), $this->user('p2-v2@example.com', 'p2v2', 'PLAYER'), null, 'p2');
        $register->execute($tournament->fresh(), $this->user('p3-v2@example.com', 'p3v2', 'PLAYER'), null, 'p3');
        $this->travelTo($tournament->start_at->copy()->addSecond());
        app(V2TournamentLifecycle::class)->reconcile($tournament->fresh());

        self::assertSame(TournamentStatus::ONGOING, $tournament->fresh()->status);
        self::assertSame(3, GameMatch::query()->where('tournament_id', $tournament->id)->count());
        self::assertSame(1, GameMatch::query()->where('tournament_id', $tournament->id)->whereNull('player_b_registration_id')->where('status', MatchStatus::COMPLETED)->count());
    }

    public function test_empty_occurrence_is_cancelled_at_start_and_can_be_purged_at_end(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        self::assertNotNull($tournament);

        $this->travelTo($tournament->start_at->copy()->addSecond());
        app(V2TournamentLifecycle::class)->reconcile($tournament);

        $cancelled = $tournament->fresh();
        self::assertSame(TournamentStatus::CANCELLED, $cancelled->status);
        self::assertSame(0, $cancelled->cancellation->affected_participant_count);
        self::assertFalse($cancelled->cancellation->refund_required);

        Tournament::query()->whereKey($cancelled->id)->update(['end_at' => now()->subSecond()]);
        self::assertSame(1, app(PurgeEmptyV2OccurrencesAction::class)->execute());
        self::assertNull(Tournament::withTrashed()->find($cancelled->id));
    }

    public function test_single_paid_entry_is_cancelled_at_start_and_keeps_its_refund_audit_record(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $template->update(['entry_fee' => '1.00']);
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->fresh()->scheduleSlots->firstOrFail(), $this->admin);
        self::assertNotNull($tournament);

        $player = $this->user('solo-refund@example.com', 'solorefund', 'PLAYER');
        $wallet = $this->walletFor($player, '10.00');
        $registration = app(RegisterForV2TournamentAction::class)->execute($tournament, $player, null, 'solo-refund');
        self::assertSame('9.00', $wallet->fresh()->cached_balance);

        $this->travelTo($tournament->start_at->copy()->addSecond());
        app(V2TournamentLifecycle::class)->reconcile($tournament);

        self::assertSame(TournamentStatus::CANCELLED, $tournament->fresh()->status);
        self::assertDatabaseHas('refunds', ['tournament_id' => $tournament->id, 'registration_id' => $registration->id, 'amount' => '1.00']);
        self::assertSame('10.00', $wallet->fresh()->cached_balance);
        self::assertNotNull(Tournament::query()->find($tournament->id));
    }

    public function test_draw_creates_new_attempt_and_consistent_results_complete_match(): void
    {
        [$match, $p1, $p2] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $p1->id, MatchOutcome::DRAW);
        $submit->execute($match->fresh(), $p2->id, MatchOutcome::DRAW);

        self::assertSame(2, $match->fresh()->active_attempt_number);
        self::assertSame(MatchStatus::IN_PROGRESS, $match->fresh()->status);
        Livewire::actingAs($p1)
            ->test(MatchDetail::class, ['uuid' => $match->uuid])
            ->assertSee('Rematch required')
            ->assertSee('Play again and submit a new result.')
            ->assertDontSee('Ready confirmed — play now');
        $this->assertDatabaseHas('notifications', ['user_id' => $p1->id, 'type' => 'match_rematch', 'title' => 'Rematch Required']);
        $this->assertDatabaseHas('notifications', ['user_id' => $p2->id, 'type' => 'match_rematch', 'title' => 'Rematch Required']);

        $submit->execute($match->fresh(), $p1->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $p2->id, MatchOutcome::LOSS);
        self::assertSame(MatchStatus::COMPLETED, $match->fresh()->status);
        self::assertSame(2, $match->attempts()->count());
    }

    public function test_conflicting_v2_results_create_a_dispute_for_admin_review(): void
    {
        [$match, $p1, $p2] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $p1->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $p2->id, MatchOutcome::WIN);

        self::assertSame(MatchStatus::DISPUTED, $match->fresh()->status);
        self::assertDatabaseHas('match_disputes', [
            'match_id' => $match->id,
            'reason' => 'V2 result submissions conflict.',
        ]);
    }

    public function test_first_submitter_wins_after_five_minute_non_response_even_when_reporting_loss(): void
    {
        [$match, $p1] = $this->activeTwoPlayerMatch();
        app(SubmitV2MatchResultAction::class)->execute($match, $p1->id, MatchOutcome::LOSS);
        $this->travelTo(now()->addMinutes(5)->addSecond());

        (new ResolveV2ResultTimeoutsJob)->handle(app(ResolveV2ResultTimeoutAction::class));

        self::assertSame(MatchStatus::COMPLETED, $match->fresh()->status);
        self::assertSame($match->playerARegistration->includesUser($p1->id)
            ? $match->player_a_registration_id
            : $match->player_b_registration_id, $match->fresh()->winner_registration_id);
        self::assertSame('opponent_submission_timeout', $match->fresh()->resolution_reason);
    }

    public function test_first_submission_queues_exact_timeout_and_rejects_a_response_at_the_deadline(): void
    {
        Queue::fake();
        [$match, $p1, $p2] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $p1->id, MatchOutcome::WIN);
        $attempt = $match->attempts()->firstOrFail();

        Queue::assertPushed(ResolveV2ResultTimeoutJob::class, fn (ResolveV2ResultTimeoutJob $job): bool => $job->attemptId === $attempt->id);

        $this->travelTo($attempt->result_deadline_at);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The result submission deadline has passed.');
        $submit->execute($match->fresh(), $p2->id, MatchOutcome::LOSS);
    }

    public function test_cancellation_uses_snapshotted_half_threshold_and_immutable_vote(): void
    {
        $template = $this->template('daily', 8, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $players = [
            $this->user('cancel1@example.com', 'cancel1', 'PLAYER'),
            $this->user('cancel2@example.com', 'cancel2', 'PLAYER'),
            $this->user('cancel3@example.com', 'cancel3', 'PLAYER'),
            $this->user('cancel4@example.com', 'cancel4', 'PLAYER'),
        ];
        $register = app(RegisterForV2TournamentAction::class);
        $registrations = collect($players)->map(fn (User $player, int $i) => $register->execute($tournament->fresh(), $player, null, "cancel{$i}"));
        $request = app(RequestV2CancellationAction::class)->execute($registrations[0], $players[0]);

        self::assertSame([3, 2], [$request->eligible_voter_count, $request->required_approvals]);
        app(VoteOnV2CancellationAction::class)->execute($request, $players[1], true);
        self::assertSame('pending', $request->fresh()->status);
        app(VoteOnV2CancellationAction::class)->execute($request->fresh(), $players[2], true);

        self::assertSame('approved', $request->fresh()->status);
        self::assertSame('cancelled', $registrations[0]->fresh()->status->value);
    }

    public function test_configuration_is_locked_by_join_and_start_at_the_model_boundary(): void
    {
        $template = $this->template('daily', 8, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $tournament->update(['max_participants' => 16]);
        app(RegisterForV2TournamentAction::class)->execute(
            $tournament->fresh(),
            $this->user('lock-v2@example.com', 'lockv2', 'PLAYER'),
            null,
            'lock-id',
        );
        $tournament->fresh()->update(['description' => 'Presentation remains editable before start.']);

        $this->expectException(LogicException::class);
        $tournament->fresh()->update(['max_participants' => 32]);
    }

    public function test_player_can_only_hold_one_active_slot_per_template_and_recurrence_period(): void
    {
        $template = app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'Grouped Daily Tournament',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'max_teams' => 4,
            'entry_fee' => '0.00',
            'full_first_bps' => 9000,
            'full_second_bps' => 0,
            'slots' => [
                ['label' => 'Evening A', 'local_start_time' => '20:00'],
                ['label' => 'Evening B', 'local_start_time' => '21:00'],
            ],
        ]);
        $now = CarbonImmutable::now('UTC')->startOfDay()->setTime(12, 0);
        $first = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots[0], $this->admin, $now);
        $second = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots[1], $this->admin, $now);
        $player = $this->user('one-slot@example.com', 'oneslot', 'PLAYER');

        app(RegisterForV2TournamentAction::class)->execute($first, $player, null, 'one-slot-a');

        $this->expectException(LogicException::class);
        app(RegisterForV2TournamentAction::class)->execute($second, $player, null, 'one-slot-b');
    }

    public function test_unresolved_final_stays_ongoing_then_admin_can_settle_without_champion_after_24_hours(): void
    {
        [$match, $p1, $p2] = $this->activeTwoPlayerMatch();
        $tournament = $match->tournament->fresh();
        Tournament::query()->whereKey($tournament->id)->update([
            'end_at' => now()->subDay()->subSecond(),
            'financial_finalized_at' => now(),
            'finalized_gross_pool' => '9.99',
            'finalized_commission_amount' => '1.50',
            'finalized_first_prize' => '8.49',
            'finalized_second_prize' => '0.00',
            'payout_status' => 'pending',
        ]);
        $tournament->refresh();
        $this->walletFor($p1);
        $this->walletFor($p2);
        $match->forceFill(['stalled_deadline_at' => now()->subSecond()])->save();

        app(V2StalledMatchService::class)->expire($match->id);
        $held = $match->fresh();
        self::assertSame(MatchStatus::IN_PROGRESS, $held->status);
        self::assertSame('held', $tournament->fresh()->payout_status);
        self::assertNotNull($held->final_resolution_eligible_at);

        app(V2StalledMatchService::class)->escalateNoChampion($held->id);
        $dispute = MatchDispute::query()->where('match_id', $held->id)->firstOrFail();
        self::assertSame(MatchStatus::DISPUTED, $held->fresh()->status);

        app(ResolveDisputeAction::class)->execute($dispute, $this->admin, DisputeResolution::NO_CHAMPION);
        app(AwardV2PrizesAction::class)->execute($tournament->fresh());
        app(AwardV2PrizesAction::class)->execute($tournament->fresh());

        $settled = $tournament->fresh();
        self::assertSame(TournamentStatus::COMPLETED, $settled->status);
        self::assertSame('no_champion', $settled->completion_reason);
        self::assertSame('paid', $settled->payout_status);
        self::assertSame('1.00', $settled->finalized_commission_amount);
        self::assertSame('4.50', $settled->finalized_first_prize);
        self::assertSame('4.49', $settled->finalized_second_prize);
        $playerAUserId = $held->playerARegistration->user_id;
        self::assertSame('4.50', User::query()->findOrFail($playerAUserId)->wallet->cached_balance);
        $playerBUserId = $held->playerBRegistration->user_id;
        self::assertSame('4.49', User::query()->findOrFail($playerBUserId)->wallet->cached_balance);
    }

    public function test_both_admin_creation_forms_save_multiple_platforms_and_show_them_in_overviews(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-12 10:00:00', 'UTC'));
        $console = Platform::query()->create(['name' => 'PlayStation', 'slug' => 'ps-multi', 'is_active' => true]);
        $this->game->platforms()->attach($console);
        $ids = [$this->platform->id, $console->id];

        foreach (['tournament', 'head_to_head'] as $type) {
            $route = $type === 'head_to_head' ? 'admin.h2h.v2' : 'admin.tournaments.v2';
            $this->actingAs($this->admin)->get(route($route.'.create'))
                ->assertOk()->assertSee('name="platform_ids[]"', false);
            $this->post(route($route.'.store'), $this->multiPlatformPayload($ids, $type))
                ->assertSessionHasNoErrors()->assertRedirect();
            $tournament = Tournament::query()->latest('id')->firstOrFail();
            self::assertSame($ids, $tournament->platform_ids);
            self::assertSame($ids, $tournament->template->settings_json['platform_ids']);
            self::assertSame($this->platform->id, $tournament->platform_id);
            $this->get(route('admin.tournaments.v2.occurrences.show', $tournament))
                ->assertOk()->assertSee('PC, PlayStation');
            self::assertTrue(Tournament::query()->forPlatform($console->id)->whereKey($tournament->id)->exists());
            $discovery = app(V2TournamentDiscoveryService::class)
                ->paginate('upcoming', ['platform_id' => (string) $console->id, 'competition_type' => $type]);
            self::assertSame(1, $discovery->total());
        }
    }

    public function test_platform_selection_rejects_empty_duplicate_inactive_and_unrelated_platforms(): void
    {
        $other = Platform::query()->create(['name' => 'Other', 'slug' => 'other-multi', 'is_active' => true]);
        $inactive = Platform::query()->create(['name' => 'Inactive', 'slug' => 'inactive-multi', 'is_active' => false]);
        $this->game->platforms()->attach($inactive);
        foreach ([[], [$this->platform->id, $this->platform->id], [$this->platform->id, $other->id], [$this->platform->id, $inactive->id]] as $ids) {
            $response = $this->actingAs($this->admin)->post(
                route('admin.tournaments.v2.store'),
                $this->multiPlatformPayload($ids),
            );
            $response->assertSessionHasErrors();
        }
        self::assertSame(0, Tournament::query()->count());
    }

    public function test_multi_platform_selection_survives_slot_edits_and_is_locked_after_joining(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-12 10:00:00', 'UTC'));
        $console = Platform::query()->create(['name' => 'Xbox', 'slug' => 'xbox-multi', 'is_active' => true]);
        $this->game->platforms()->attach($console);
        $ids = [$this->platform->id, $console->id];
        $this->actingAs($this->admin)->post(route('admin.tournaments.v2.store'), $this->multiPlatformPayload($ids))->assertSessionHasNoErrors();
        $tournament = Tournament::query()->firstOrFail();
        $this->get(route('admin.tournaments.v2.templates.slots', $tournament->template_id))
            ->assertOk()->assertSee('name="platform_ids[]"', false);
        $this->put(route('admin.tournaments.v2.occurrences.update', $tournament), [
            'name' => 'Edited multiple platforms', 'platform_ids' => $ids,
            'max_teams' => 4, 'entry_fee' => '0.00', 'start_at' => '2026-09-12T23:00',
            'end_date' => '2026-09-13', 'waiting_result_time' => 5, 'winning_points' => 15,
        ])->assertSessionHasNoErrors()->assertRedirect();
        self::assertSame($ids, $tournament->fresh()->platform_ids);
        $player = $this->user('multi-player@example.com', 'multiplayer', 'PLAYER');
        app(RegisterForV2TournamentAction::class)->execute($tournament->fresh(), $player, null, 'XboxHandle', platformId: $console->id);
        $this->assertDatabaseHas('user_game_accounts', ['user_id' => $player->id, 'platform_id' => $console->id, 'game_id_value' => 'XboxHandle']);
        $this->expectException(LogicException::class);
        $tournament->fresh()->update(['platform_ids' => [$this->platform->id]]);
    }

    public function test_legacy_platform_remains_visible_and_filterable(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        $tournament->update(['platform_ids' => null]);
        self::assertSame([$this->platform->id], $tournament->fresh()->supportedPlatformIds());
        self::assertSame('PC', $tournament->fresh()->platform_names);
        self::assertTrue(Tournament::query()->forPlatform($this->platform->id)->whereKey($tournament->id)->exists());
    }

    private function multiPlatformPayload(array $ids, string $type = 'tournament'): array
    {
        return [
            'name' => 'Multiple Platforms '.$type, 'competition_type' => $type,
            'game_id' => $this->game->id, 'platform_ids' => $ids,
            'frequency' => 'daily', 'max_teams' => 4, 'entry_fee' => '0.00',
            'winning_points' => 15, 'waiting_result_time' => 5,
            'full_first_percent' => 90, 'full_second_percent' => 0,
            'slots' => [[
                'local_start_time' => '23:00',
                'schedule_start_at' => now()->format('Y-m-d').'T23:00',
                'schedule_end_at' => now()->addDay()->format('Y-m-d').'T23:59',
            ]],
        ];
    }

    private function activeTwoPlayerMatch(): array
    {
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $p1 = $this->user('result1@example.com', 'result1', 'PLAYER');
        $p2 = $this->user('result2@example.com', 'result2', 'PLAYER');
        $register = app(RegisterForV2TournamentAction::class);
        $register->execute($tournament, $p1, null, 'r1');
        $register->execute($tournament->fresh(), $p2, null, 'r2');
        $this->travelTo($tournament->start_at->copy()->addSecond());
        app(V2TournamentLifecycle::class)->reconcile($tournament->fresh());

        return [GameMatch::query()->where('tournament_id', $tournament->id)->firstOrFail(), $p1, $p2];
    }

    private function template(string $frequency, int $maximum, string $time, array $slot = [])
    {
        return app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'V2 Tournament',
            'frequency' => $frequency,
            'timezone' => 'UTC',
            'max_teams' => $maximum,
            'entry_fee' => '0.00',
            'full_first_bps' => $maximum === 4 ? 9000 : 7500,
            'full_second_bps' => $maximum === 4 ? 0 : 1500,
            'waiting_result_time' => 5,
            'winning_points' => 50,
            'slots' => [[
                'label' => 'Main',
                'local_start_time' => $time,
                ...$slot,
            ]],
        ]);
    }

    private function user(string $email, string $username, string $role): User
    {
        $user = User::query()->create([
            'uuid' => Str::uuid(), 'email' => $email, 'username' => $username,
            'password' => bcrypt('password'), 'email_verified_at' => now(), 'status' => 'active',
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function walletFor(User $user, string $balance = '0.00'): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['uuid' => Str::uuid(), 'cached_balance' => $balance, 'status' => WalletStatus::ACTIVE],
        );
    }
}
