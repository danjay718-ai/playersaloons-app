<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Http\Middleware\TranslateRenderedHtml;
use App\Livewire\Admin\MatchAdmin;
use App\Livewire\Admin\TournamentAdmin;
use App\Livewire\Game\GameShow;
use App\Livewire\Match\MatchDetail;
use App\Livewire\Tournament\PlatformHeadToHeadList;
use App\Livewire\Tournament\PlayerTournamentList;
use App\Livewire\Tournament\PublicTournamentList;
use App\Livewire\Tournament\TournamentDetail;
use App\Livewire\Wallet\WalletBalance;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameHeadToHeadDefault;
use App\Modules\CMS\Models\GameTournamentDefault;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\PlayerProgression;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserGameAccount;
use App\Modules\Identity\Services\PlayerProgressionService;
use App\Modules\Match\Actions\ForfeitMatchAction;
use App\Modules\Match\Actions\ResolveDisputeAction;
use App\Modules\Match\Actions\ResolveV2ResultTimeoutAction;
use App\Modules\Match\Actions\SubmitV2MatchResultAction;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Jobs\ResolveV2ResultTimeoutJob;
use App\Modules\Match\Jobs\ResolveV2ResultTimeoutsJob;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Services\V2StalledMatchService;
use App\Modules\Tournament\Actions\AwardV2PrizesAction;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Actions\ConvertTournamentSchedulesTimezoneAction;
use App\Modules\Tournament\Actions\CreateV2TournamentTemplateAction;
use App\Modules\Tournament\Actions\MaterializeV2OccurrenceAction;
use App\Modules\Tournament\Actions\PurgeEmptyV2OccurrencesAction;
use App\Modules\Tournament\Actions\RegisterForV2TournamentAction;
use App\Modules\Tournament\Actions\ResetTournamentTestingDataAction;
use App\Modules\Tournament\Actions\VoteOnV2CancellationAction;
use App\Modules\Tournament\Events\BroadcastTournamentUpdated;
use App\Modules\Tournament\Jobs\NotifyV2CancellationVotersJob;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentCancellationRequest;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\Services\CancelledOccurrenceRetention;
use App\Modules\Tournament\Services\V2TournamentDiscoveryService;
use App\Modules\Tournament\Services\V2TournamentLifecycle;
use App\Modules\Wallet\Actions\ProcessDepositAction;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\MatchOutcome;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\WalletStatus;
use App\Shared\Support\DecimalMoney;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_player_and_guest_tournament_lists_render_each_v2_occurrence_without_slot_picker(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 10:00:00', 'UTC'));
        $slots = collect(range(12, 21))->map(fn (int $hour): array => [
            'label' => 'Slot '.$hour,
            'local_start_time' => sprintf('%02d:00', $hour),
        ])->all();
        $template = app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'Direct Instance Cup',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'max_teams' => 4,
            'entry_fee' => '5.00',
            'full_first_bps' => 9000,
            'full_second_bps' => 0,
            'slots' => $slots,
        ]);
        $occurrences = $template->scheduleSlots->map(
            fn ($slot) => app(MaterializeV2OccurrenceAction::class)->execute($slot, $this->admin),
        );
        $first = $occurrences->first();
        $second = $occurrences->get(1);
        $player = $this->user('instance-list@example.com', 'instancelist', 'PLAYER');
        $headToHeadTemplate = app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'Direct H2H Instance',
            'competition_type' => CompetitionType::HEAD_TO_HEAD->value,
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'max_teams' => 2,
            'entry_fee' => '1.00',
            'full_first_bps' => 9000,
            'full_second_bps' => 0,
            'slots' => [['label' => 'H2H', 'local_start_time' => '22:00']],
        ]);
        $headToHead = app(MaterializeV2OccurrenceAction::class)->execute(
            $headToHeadTemplate->scheduleSlots->firstOrFail(),
            $this->admin,
        );

        $paginated = app(V2TournamentDiscoveryService::class)->paginateOccurrences('upcoming', [
            'competition_type' => 'tournament',
            'frequency' => 'daily',
        ], 9);
        self::assertSame(10, $paginated->total());
        self::assertSame(9, $paginated->count());
        self::assertSame(2, $paginated->lastPage());

        $playerList = Livewire::actingAs($player)
            ->test(PlayerTournamentList::class)
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 10 && $items->count() === 9)
            ->assertViewHas('tournamentGroups', null)
            ->assertSee('/tournaments/'.$first->uuid.'/view', false)
            ->assertSee('/tournaments/'.$second->uuid.'/view', false)
            ->assertSee('Starts in')
            ->assertSee('Joined players')
            ->assertSee('Max teams')
            ->assertSee('Platforms')
            ->assertSee('Showing 1–9 of 10 results')
            ->assertDontSee('Featured Daily Tournaments')
            ->assertDontSee('View Available Slots')
            ->assertDontSee('Choose a currently available tournament slot');

        $playerList->set('maxTeams', '8')
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 0)
            ->set('maxTeams', 'custom')
            ->set('customMaxTeams', '4')
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 10)
            ->call('setPage', 2, 'page')
            ->assertViewHas('tournaments', fn ($items) => $items->currentPage() === 2 && $items->count() === 1);

        $publicList = Livewire::test(PublicTournamentList::class)
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 10 && $items->count() === 9)
            ->assertViewHas('tournamentGroups', null)
            ->assertSee('/tournaments/'.$first->uuid.'/view?view=guest', false)
            ->assertDontSee('View Available Slots');

        $publicList->set('competitionType', 'head_to_head')
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 1 && $items->first()->is($headToHead))
            ->assertViewHas('tournamentGroups', null)
            ->assertSee('/tournaments/'.$headToHead->uuid.'/view?view=guest', false)
            ->assertDontSee('View Slots');

        Livewire::withQueryParams(['view' => 'guest'])
            ->test(PlatformHeadToHeadList::class)
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 1 && $items->first()->is($headToHead))
            ->assertViewHas('tournamentGroups', null)
            ->assertSee('Click a game card to view its available head-to-head matches.')
            ->assertSee('View Game')
            ->assertSee('1v1 only')
            ->assertDontSee('All max teams')
            ->assertDontSee('Featured Head-to-Head Matches')
            ->assertDontSee('View Slots');

        Livewire::withQueryParams(['tab' => 'browse', 'competitionType' => 'head_to_head', 'view' => 'guest'])
            ->test(GameShow::class, ['game' => $this->game])
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 1 && $items->first()->is($headToHead))
            ->assertViewHas('tournamentGroups', null)
            ->assertSee('/tournaments/'.$headToHead->uuid.'/view?view=guest', false)
            ->assertDontSee('Featured Head-to-Head')
            ->assertDontSee('View Slots');
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
            'reason' => 'participation', 'amount' => 4, 'metadata' => json_encode(['version' => 2]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        PlayerProgression::query()->create(['user_id' => $player->id, 'experience_points' => 4, 'level' => 1, 'tournaments_completed' => 1]);

        $summary = app(ResetTournamentTestingDataAction::class)->execute();

        self::assertSame(1, $summary['tournaments']);
        $this->assertDatabaseCount('tournaments', 0);
        $this->assertDatabaseCount('tournament_templates', 0);
        $this->assertDatabaseMissing('ledger_entries', ['id' => $ledgerId]);
        $this->assertDatabaseMissing('wallet_transactions', ['ledger_entry_id' => $ledgerId]);
        $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'cached_balance' => '0.00']);
        $this->assertDatabaseHas('player_progressions', ['user_id' => $player->id, 'experience_points' => 0, 'tournaments_completed' => 0]);
    }

    public function test_v2_participation_awards_four_xp_once(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        $player = $this->user('participation-xp@example.com', 'participationxp', 'PLAYER');
        $progression = app(PlayerProgressionService::class);

        $progression->awardV2TournamentParticipation($player->id, $tournament->id);
        $progression->awardV2TournamentParticipation($player->id, $tournament->id);

        $this->assertDatabaseHas('player_experience_awards', [
            'user_id' => $player->id,
            'source_id' => $tournament->id,
            'reason' => 'participation',
            'amount' => 4,
        ]);
        $this->assertDatabaseHas('player_progressions', [
            'user_id' => $player->id,
            'experience_points' => 4,
            'tournaments_completed' => 1,
        ]);
        self::assertSame(1, DB::table('player_experience_awards')
            ->where('user_id', $player->id)
            ->where('reason', 'participation')
            ->count());
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

    public function test_exact_tournament_status_filter_clears_conflicting_status_group_tab(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TournamentAdmin::class)
            ->set('statusTab', 'active')
            ->call('setStatusFilter', TournamentStatus::CANCELLED->value)
            ->assertSet('statusFilter', TournamentStatus::CANCELLED->value)
            ->assertSet('statusTab', 'all');
    }

    public function test_admin_badges_count_visible_schedules_and_exclude_head_to_head_and_archived_occurrences(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $template = $this->template('daily', 4, '23:59');
        $slot = $template->scheduleSlots->firstOrFail();
        $first = app(MaterializeV2OccurrenceAction::class)->execute($slot, $this->admin);
        $second = app(MaterializeV2OccurrenceAction::class)->execute($slot, $this->admin, CarbonImmutable::now()->addDay());
        foreach ([$first, $second] as $occurrence) {
            app(CancelTournamentAction::class)->execute($occurrence, $this->admin, 'Test cancellation');
        }
        $h2hTemplate = $this->template('daily', 2, '23:58');
        $h2hTemplate->forceFill(['competition_type' => CompetitionType::HEAD_TO_HEAD])->save();
        $h2h = app(MaterializeV2OccurrenceAction::class)->execute($h2hTemplate->scheduleSlots->firstOrFail(), $this->admin);
        app(CancelTournamentAction::class)->execute($h2h, $this->admin, 'Test H2H cancellation');

        $page = Livewire::actingAs($this->admin)->test(TournamentAdmin::class)
            ->assertViewHas('countCancelled', 1)
            ->assertViewHas('countAll', 1)
            ->assertViewHas('countActive', 0)
            ->set('statusTab', 'cancelled')
            ->assertViewHas('v2Templates', fn ($rows) => $rows->total() === 1)
            ->assertSee($template->name)
            ->set('statusTab', 'all')
            ->assertViewHas('v2Templates', fn ($rows) => $rows->total() === 1);

        $first->delete();
        $second->delete();
        $page->call('$refresh')
            ->assertViewHas('countCancelled', 0)
            ->assertViewHas('countAll', 0)
            ->assertViewHas('v2Templates', fn ($rows) => $rows->total() === 0);
    }

    public function test_admin_head_to_head_badges_use_the_same_frequency_and_date_filters_as_the_list(): void
    {
        $this->withoutMiddleware(TranslateRenderedHtml::class);
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        foreach (['daily', 'weekly'] as $frequency) {
            $template = $this->template($frequency, 2, '23:59', ['day_of_week' => 3]);
            $template->forceFill(['competition_type' => CompetitionType::HEAD_TO_HEAD])->save();
            $occurrence = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
            app(CancelTournamentAction::class)->execute($occurrence, $this->admin, 'Test cancellation');
        }

        $this->actingAs($this->admin)->get(route('admin.h2h.index', ['tab' => 'daily', 'status_tab' => 'cancelled']))
            ->assertOk()->assertViewHas('countCancelled', 1)->assertViewHas('countAll', 1)
            ->assertViewHas('templates', fn ($rows) => $rows->total() === 1);
        $this->get(route('admin.h2h.index', ['tab' => 'weekly', 'status_tab' => 'cancelled', 'start_date' => '2027-01-01']))
            ->assertOk()->assertViewHas('countCancelled', 0)->assertViewHas('countAll', 0)
            ->assertViewHas('templates', fn ($rows) => $rows->total() === 0);
    }

    public function test_admin_tabs_reset_the_v2_paginator_and_recover_when_a_page_becomes_empty(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        for ($i = 0; $i < 3; $i++) {
            $template = $this->template('daily', 4, '23:59');
            app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        }
        $cancelled = Tournament::query()->firstOrFail();
        app(CancelTournamentAction::class)->execute($cancelled, $this->admin, 'Test cancellation');

        $page = Livewire::actingAs($this->admin)->test(TournamentAdmin::class)
            ->set('perPage', 1)
            ->call('setPage', 2, 'v2Page')
            ->assertViewHas('v2Templates', fn ($rows) => $rows->currentPage() === 2)
            ->set('statusTab', 'cancelled')
            ->assertViewHas('countCancelled', 1)
            ->assertViewHas('v2Templates', fn ($rows) => $rows->currentPage() === 1 && $rows->count() === 1)
            ->set('statusTab', 'all')
            ->call('setPage', 3, 'v2Page');

        $cancelled->delete();
        $page->call('$refresh')
            ->assertViewHas('countAll', 2)
            ->assertViewHas('v2Templates', fn ($rows) => $rows->currentPage() === 1 && $rows->count() === 1);
    }

    #[DataProvider('discoveryViewers')]
    public function test_open_discovery_lists_remove_cancelled_occurrences_on_automatic_refresh(string $component, bool $player, bool $headToHead): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $template = $this->template('daily', $headToHead ? 2 : 4, '23:59');
        if ($headToHead) {
            $template->forceFill(['competition_type' => CompetitionType::HEAD_TO_HEAD])->save();
        }
        $occurrence = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        if ($player) {
            Livewire::actingAs($this->user('discovery-player@example.com', 'discoveryplayer', 'PLAYER'));
        }
        $page = Livewire::test($component)
            ->assertSee('wire:poll.10s.visible', escape: false)
            ->assertSee('tournamentListRealtime', escape: false)
            ->assertViewHas('tournaments', fn ($rows) => $rows->total() === 1);

        app(CancelTournamentAction::class)->execute($occurrence, $this->admin, 'Test cancellation');

        $page->call('$refresh')
            ->assertViewHas('tournaments', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('featuredTournaments', fn ($rows) => $rows->total() === 0);
    }

    public static function discoveryViewers(): array
    {
        return [
            'guest tournaments' => [PublicTournamentList::class, false, false],
            'player tournaments' => [PlayerTournamentList::class, true, false],
            'guest H2H' => [PlatformHeadToHeadList::class, false, true],
            'player H2H' => [PlatformHeadToHeadList::class, true, true],
        ];
    }

    public function test_start_time_scheduler_generates_the_bracket_and_open_detail_loads_it_on_refresh(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $template = $this->template('daily', 4, '10:05');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        $player = $this->user('scheduled-player@example.com', 'scheduledplayer', 'PLAYER');
        $opponent = $this->user('scheduled-opponent@example.com', 'scheduledopponent', 'PLAYER');
        $register = app(RegisterForV2TournamentAction::class);
        $register->execute($tournament, $player, null, 'scheduled-player');
        $register->execute($tournament->fresh(), $opponent, null, 'scheduled-opponent');

        $page = Livewire::actingAs($player)->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->call('loadSection', 'matches')
            ->assertViewHas('rounds', fn ($rounds) => $rounds->isEmpty());
        $this->travelTo($tournament->start_at);
        $this->artisan('tournaments:reconcile-lifecycle')->assertSuccessful();

        self::assertSame(TournamentStatus::ONGOING, $tournament->fresh()->status);
        self::assertSame(1, GameMatch::query()->where('tournament_id', $tournament->id)->where('status', MatchStatus::IN_PROGRESS)->count());
        $page->call('$refresh')->assertViewHas('rounds', fn ($rounds) => $rounds->isNotEmpty());
    }

    public function test_tournament_changes_are_broadcast_to_detail_and_discovery_channels(): void
    {
        $event = new BroadcastTournamentUpdated('test-uuid', 'TournamentCancelled');
        self::assertSame(['tournament.test-uuid', 'tournaments'], array_map(fn ($channel) => $channel->name, $event->broadcastOn()));
        self::assertSame(['change' => 'TournamentCancelled', 'match_uuid' => null], $event->broadcastWith());
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
            ->get(route('admin.h2h.v2.create'))
            ->assertOk()
            ->assertSee('Result response time')
            ->assertSee('Default 5 minutes');

        $this->actingAs($this->admin)
            ->post('/admin/head-to-head', [
                'competition_type' => 'head_to_head',
                'game_id' => $this->game->id,
                'platform_id' => $this->platform->id,
                'name' => 'Admin-created H2H',
                'description' => '<p>Dedicated 1v1 rules.</p>',
                'rules' => '<p>Report results after each match.</p>',
                'frequency' => 'one_time',
                // The browser does not allow this in the dedicated UI. The
                // request test proves the server still persists exactly two.
                'max_teams' => 64,
                'entry_fee' => '1.00',
                'winning_points' => 15,
                'waiting_result_time' => 8,
                'full_first_percent' => 90,
                'full_second_percent' => 0,
                'slots' => [[
                    'label' => 'Evening',
                    'local_start_time' => '18:00',
                    'schedule_start_at' => $start->format('Y-m-d H:i:s'),
                    'schedule_end_at' => $end->format('Y-m-d H:i:s'),
                    'waiting_result_time' => 3,
                ]],
            ])
            ->assertRedirect(route('admin.h2h.index'));

        $this->assertDatabaseHas('tournament_templates', [
            'name' => 'Admin-created H2H',
            'competition_type' => CompetitionType::HEAD_TO_HEAD->value,
            'max_participants' => 2,
            'min_participants' => 2,
        ]);

        $template = TournamentTemplate::query()->where('name', 'Admin-created H2H')->firstOrFail();
        $this->assertSame(8, $template->settings_json['waiting_result_time']);
        $this->assertSame(3, (int) $template->scheduleSlots->firstOrFail()->overrides_json['waiting_result_time']);
        $this->assertSame(3, Tournament::query()->where('template_id', $template->id)->firstOrFail()->waiting_result_time);
    }

    public function test_join_prompts_for_a_fresh_game_id_without_updating_the_saved_game_account(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-12 10:00:00', 'UTC'));
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        $player = $this->user('fresh-game-id@example.com', 'freshgameid', 'PLAYER');
        $account = UserGameAccount::query()->create([
            'user_id' => $player->id,
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'game_id_value' => 'OldReusableHandle',
        ]);

        Livewire::actingAs($player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSet('gameIdValue', '')
            ->assertSee('Registration details')
            ->set('gameIdValue', 'StaleDraft')
            ->call('prepareRegistrationPrompt')
            ->assertSet('gameIdValue', '')
            ->set('gameIdValue', 'FreshMatchHandle')
            ->call('register')
            ->assertHasNoErrors()
            ->assertDispatched('tournament-registration-completed');

        $this->assertDatabaseHas('tournament_registrations', [
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'platform_id' => $this->platform->id,
            'game_id_value' => 'FreshMatchHandle',
        ]);
        $this->assertSame('OldReusableHandle', $account->fresh()->game_id_value);

        $nextTemplate = $this->template('daily', 4, '23:58');
        $nextTournament = app(MaterializeV2OccurrenceAction::class)->execute($nextTemplate->scheduleSlots->firstOrFail(), $this->admin);
        Livewire::actingAs($player)
            ->test(TournamentDetail::class, ['uuid' => $nextTournament->uuid])
            ->assertSet('gameIdValue', '');
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
            'start_at' => now()->subDays(31),
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
        self::assertSame(1, GameMatch::query()->where('tournament_id', $tournament->id)->where('status', MatchStatus::IN_PROGRESS)->count());
        self::assertSame(1, GameMatch::query()->where('tournament_id', $tournament->id)->where('status', MatchStatus::PENDING)
            ->where(fn ($query) => $query->whereNotNull('player_a_registration_id')->orWhereNotNull('player_b_registration_id'))->count());
    }

    public function test_full_tournament_starts_matches_as_soon_as_registration_locks(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        self::assertTrue($tournament->start_at->isFuture());

        $register = app(RegisterForV2TournamentAction::class);
        for ($i = 1; $i <= 4; $i++) {
            $register->execute(
                $tournament->fresh(),
                $this->user("full{$i}-v2@example.com", "full{$i}v2", 'PLAYER'),
                null,
                "full{$i}",
            );
        }

        self::assertSame(TournamentStatus::ONGOING, $tournament->fresh()->status);
        self::assertSame(2, GameMatch::query()->where('tournament_id', $tournament->id)->where('status', MatchStatus::IN_PROGRESS)->count());
        self::assertSame(1, GameMatch::query()->where('tournament_id', $tournament->id)->where('status', MatchStatus::PENDING)->count());
    }

    public function test_non_adjacent_winners_pair_immediately_and_the_bracket_shows_their_actual_destination(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $template = $this->template('daily', 8, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        for ($i = 1; $i <= 8; $i++) {
            app(RegisterForV2TournamentAction::class)->execute($tournament->fresh(), $this->user("random{$i}@example.com", "random{$i}", 'PLAYER'), null, "random{$i}");
        }
        $firstRound = $tournament->rounds()->where('round_number', 1)->firstOrFail()->matches()->orderBy('id')->get();
        $first = $firstRound[0];
        $third = $firstRound[2];
        $page = Livewire::actingAs($first->playerARegistration->user)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSet('activeTab', 'matches')
            ->assertViewHas('bracketLoaded', true);

        $this->completeV2Match($first);
        $destination = $tournament->rounds()->where('round_number', 2)->firstOrFail()->matches()
            ->where('player_a_registration_id', $first->player_a_registration_id)->firstOrFail();
        self::assertSame(MatchStatus::PENDING, $destination->status);
        self::assertNull($destination->player_b_registration_id);

        // Match 3 can pair with Match 1 without waiting for Match 2 or Match 4.
        $this->completeV2Match($third);
        self::assertSame($third->player_a_registration_id, $destination->fresh()->player_b_registration_id);
        self::assertSame(MatchStatus::IN_PROGRESS, $destination->fresh()->status);
        self::assertSame(MatchStatus::IN_PROGRESS, $firstRound[1]->fresh()->status);
        self::assertSame(MatchStatus::IN_PROGRESS, $firstRound[3]->fresh()->status);
        $page->call('$refresh')->assertSee("Advances to match #{$destination->id}");

        // Replayed events must not duplicate either player or restart their match.
        foreach ([$first, $third] as $source) {
            MatchCompleted::dispatch($source->id, $tournament->id, $source->player_a_registration_id);
        }
        self::assertSame(MatchStatus::IN_PROGRESS, $destination->fresh()->status);
        $assigned = $tournament->rounds()->where('round_number', 2)->firstOrFail()->matches()->get();
        self::assertSame(2, $assigned->sum(fn ($match) => (int) ($match->player_a_registration_id !== null) + (int) ($match->player_b_registration_id !== null)));
    }

    #[DataProvider('tournamentPairingCounts')]
    public function test_random_advancement_pairs_every_available_player_and_completes_without_duplicates(int $capacity, int $count): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $template = $this->template('daily', $capacity, '10:05');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        for ($i = 1; $i <= $count; $i++) {
            app(RegisterForV2TournamentAction::class)->execute($tournament->fresh(), $this->user("bye{$i}@example.com", "bye{$i}", 'PLAYER'), null, "bye{$i}");
        }
        $this->travelTo($tournament->start_at);
        $this->artisan('tournaments:reconcile-lifecycle')->assertSuccessful();
        $rounds = Round::query()->whereHas('bracket', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->orderBy('round_number')->get();
        $expectedPlayers = $count;
        $expectedRegistrations = $tournament->participants()->pluck('registration_id')->all();
        foreach ($rounds as $round) {
            $matches = $round->matches()->orderBy('id')->get();
            self::assertSame((int) ceil($expectedPlayers / 2), $matches->count());
            self::assertSame(intdiv($expectedPlayers, 2), $matches->where('status', MatchStatus::IN_PROGRESS)->count());
            self::assertSame($expectedPlayers % 2, $matches->where('status', MatchStatus::COMPLETED)->count());
            $players = $matches->flatMap(fn ($match) => [$match->player_a_registration_id, $match->player_b_registration_id])->filter();
            self::assertSame($expectedPlayers, $players->count());
            self::assertSame($expectedPlayers, $players->unique()->count());
            self::assertEqualsCanonicalizing($expectedRegistrations, $players->all());
            foreach ($matches->where('status', MatchStatus::IN_PROGRESS) as $pair) {
                self::assertNotNull($pair->player_a_registration_id);
                self::assertNotNull($pair->player_b_registration_id);
                self::assertNull($pair->winner_registration_id);
            }
            foreach ($matches->where('status', MatchStatus::COMPLETED) as $bye) {
                self::assertNull($bye->player_b_registration_id);
                self::assertSame($bye->player_a_registration_id, $bye->winner_registration_id);
            }
            // Finish in reverse order to exercise random allocation and the last bye.
            foreach ($matches->where('status', MatchStatus::IN_PROGRESS)->reverse() as $match) {
                $this->completeV2Match($match);
                MatchCompleted::dispatch($match->id, $tournament->id, $match->player_a_registration_id);

                // Waiting for an unfinished match must never grant an early bye.
                $nextRound = $rounds->firstWhere('round_number', $round->round_number + 1);
                if ($nextRound !== null) {
                    $sourceFinished = $round->matches()->whereNull('winner_registration_id')->doesntExist();
                    $nextByes = $nextRound->matches()->where('status', MatchStatus::COMPLETED)
                        ->whereNull('player_b_registration_id')->count();
                    self::assertSame($sourceFinished ? $matches->count() % 2 : 0, $nextByes);
                }
            }
            $expectedRegistrations = $round->matches()->pluck('winner_registration_id')->all();
            $expectedPlayers = (int) ceil($expectedPlayers / 2);
        }
        self::assertSame(TournamentStatus::COMPLETED, $tournament->fresh()->status);
    }

    public static function tournamentPairingCounts(): array
    {
        $cases = ['five players' => [16, 5], 'six players' => [16, 6]];
        foreach ([16, 32, 64, 128] as $capacity) {
            foreach ([$capacity, $capacity - 1, $capacity - 2, intdiv($capacity, 2) + 1, intdiv($capacity, 2) + 2] as $count) {
                $cases["{$count} players in {$capacity} slots"] = [$capacity, $count];
            }
        }

        return $cases;
    }

    public function test_tournament_tabs_default_to_the_bracket_and_put_overview_and_activity_last(): void
    {
        [$match, $player] = $this->activeTwoPlayerMatch();
        foreach ([$player, $this->admin] as $viewer) {
            Livewire::actingAs($viewer)->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
                ->assertSet('activeTab', 'matches')
                ->assertViewHas('bracketLoaded', true)
                ->assertSeeInOrder(['Fixtures & Bracket', 'Players (', 'Overview', 'Activity']);
        }
        Livewire::actingAs($player)->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSeeInOrder(["selectTab('matches')", "selectTab('submit-results')", "selectTab('participants')"], escape: false);
        Livewire::actingAs($player)->withQueryParams(['activeTab' => 'overview'])
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSet('activeTab', 'overview');
    }

    private function completeV2Match(GameMatch $match): void
    {
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match->fresh(), $match->playerARegistration->user_id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $match->playerBRegistration->user_id, MatchOutcome::LOSS);
    }

    public function test_expired_v2_match_timer_does_not_forfeit_current_or_future_match(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        $register = app(RegisterForV2TournamentAction::class);
        for ($i = 1; $i <= 4; $i++) {
            $register->execute(
                $tournament->fresh(),
                $this->user("timer{$i}-v2@example.com", "timer{$i}v2", 'PLAYER'),
                null,
                "timer{$i}",
            );
        }

        $roundOneMatch = GameMatch::query()
            ->where('tournament_id', $tournament->id)
            ->whereHas('round', fn ($round) => $round->where('round_number', 1))
            ->firstOrFail();
        $roundOneMatch->forceFill(['stalled_deadline_at' => now()->subSecond()])->save();

        app(V2StalledMatchService::class)->expire($roundOneMatch->id);

        self::assertSame(MatchStatus::IN_PROGRESS, $roundOneMatch->fresh()->status);
        self::assertNull($roundOneMatch->fresh()->stalled_deadline_at);
        self::assertSame(0, GameMatch::query()->where('tournament_id', $tournament->id)->where('status', MatchStatus::FORFEITED)->count());
        self::assertSame(1, GameMatch::query()
            ->where('tournament_id', $tournament->id)
            ->whereHas('round', fn ($round) => $round->where('round_number', 2))
            ->where('status', MatchStatus::PENDING)
            ->count());
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

        $this->travelTo(app(CancelledOccurrenceRetention::class)->eligibleAt($cancelled)->addSecond());
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

        $this->travelTo(app(CancelledOccurrenceRetention::class)->eligibleAt($tournament->fresh())->addSecond());
        self::assertSame(1, app(PurgeEmptyV2OccurrencesAction::class)->execute());
        self::assertNull(Tournament::query()->find($tournament->id));
        self::assertNotNull(Tournament::withTrashed()->find($tournament->id));
        self::assertDatabaseHas('refunds', ['tournament_id' => $tournament->id, 'registration_id' => $registration->id, 'amount' => '1.00']);
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
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertSee('This is a rematch — play again')
            ->assertSee('The previous attempt ended without a winner. Play this rematch and submit a new result.')
            ->assertDontSee('Ready confirmed — play now');
        $this->assertDatabaseHas('notifications', ['user_id' => $p1->id, 'type' => 'match_rematch', 'title' => 'Rematch Required']);
        $this->assertDatabaseHas('notifications', ['user_id' => $p2->id, 'type' => 'match_rematch', 'title' => 'Rematch Required']);

        $submit->execute($match->fresh(), $p1->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $p2->id, MatchOutcome::LOSS);
        self::assertSame(MatchStatus::COMPLETED, $match->fresh()->status);
        self::assertSame(2, $match->attempts()->count());
    }

    public function test_standalone_v2_match_room_redirects_to_the_exact_tournament_match(): void
    {
        [$match, $player] = $this->activeTwoPlayerMatch();

        Livewire::actingAs($player)
            ->test(MatchDetail::class, ['uuid' => $match->uuid])
            ->assertRedirect(route('tournaments.view', ['uuid' => $match->tournament->uuid])
                .'?'.http_build_query([
                    'activeTab' => 'submit-results',
                    'match' => $match->uuid,
                ]));
    }

    public function test_v2_participant_can_submit_results_inside_the_tournament_view(): void
    {
        [$match, $player] = $this->activeTwoPlayerMatch();

        Livewire::actingAs($player)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee('Submit Result')
            ->assertSee('Submit Match Results')
            ->assertSee($match->uuid, escape: false)
            ->assertDontSee('MATCH ROOM')
            ->call('openMatch', $match->uuid)
            ->assertSet('activeTab', 'submit-results')
            ->assertSet('selectedMatchUuid', $match->uuid)
            ->assertDispatched('tournament-content-opened', tournamentUuid: $match->tournament->uuid, matchUuid: $match->uuid, tab: 'submit-results', focus: null);
    }

    #[DataProvider('competitionTypes')]
    public function test_join_button_uses_the_competition_type(CompetitionType $type): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-04 10:00:00', 'UTC'));
        $template = $this->template('daily', $type === CompetitionType::HEAD_TO_HEAD ? 2 : 4, '23:59', [], $type);
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $player = $this->user('join-label@example.com', 'joinlabel', 'PLAYER');

        Livewire::actingAs($player)->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSee($type === CompetitionType::HEAD_TO_HEAD ? 'Join Competition' : 'Join Tournament')
            ->assertDontSee($type === CompetitionType::HEAD_TO_HEAD ? 'Join Tournament' : 'Join Competition');
    }

    #[DataProvider('competitionTypes')]
    public function test_opponent_can_still_submit_after_the_first_result_and_rematches_reset_submission_status(CompetitionType $type): void
    {
        [$match, $first, $opponent] = $this->activeTwoPlayerMatch([], $type);
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $first->id, MatchOutcome::DRAW);

        Livewire::actingAs($first)->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertViewHas('currentMatchHasSubmittedResult', true)
            ->assertSee('View Result Status');

        Livewire::actingAs($opponent)->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertViewHas('currentMatchHasSubmittedResult', false)
            ->assertSee('Submit Result')
            ->assertSee('Waiting for your result')
            ->assertDontSee('View Result Status')
            ->call('openMatch', $match->uuid)
            ->assertSet('activeTab', 'submit-results');

        $submit->execute($match->fresh(), $opponent->id, MatchOutcome::DRAW);
        Livewire::actingAs($first)->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertViewHas('currentMatchHasSubmittedResult', false)
            ->assertSee('Submit Result')
            ->assertDontSee('View Result Status');
    }

    public static function competitionTypes(): array
    {
        return [
            'tournament' => [CompetitionType::TOURNAMENT],
            'head to head' => [CompetitionType::HEAD_TO_HEAD],
        ];
    }

    public function test_player_result_view_combines_match_metadata_and_places_connection_details_after_results(): void
    {
        [$match, $player] = $this->activeTwoPlayerMatch();
        Livewire::actingAs($player)->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertDontSee('Match Header Card', escape: false)
            ->assertSee('data-match-content="matchup"', escape: false)
            ->assertSeeInOrder(['Round 1', 'in progress', 'VS', 'Match in progress — play now', 'SUBMIT RESULTS', 'Submit Match Results', 'Game &amp; Connection Details'], escape: false)
            ->assertSee('Lost')
            ->assertDontSee('>Loss<', escape: false);
    }

    public static function adminRematchResolutions(): array
    {
        return [['rematch'], ['draw']];
    }

    #[DataProvider('adminRematchResolutions')]
    public function test_admin_rematch_reopens_both_players_embedded_result_pages_and_delivers_the_ruling(string $resolution): void
    {
        [$match, $first, $second] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $first->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $second->id, MatchOutcome::WIN);
        $dispute = $match->disputes()->firstOrFail();
        $originalAttempt = $match->fresh()->active_attempt_number;

        $pages = [];
        foreach ([$first, $second] as $player) {
            $pages[$player->id] = Livewire::actingAs($player)
                ->withQueryParams(['activeTab' => 'submit-results', 'match' => $match->uuid])
                ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
                ->assertSee('Result conflict detected');
        }

        // The decision must reach both players even while queued listeners are delayed.
        Queue::fake();
        Livewire::actingAs($this->admin)->test(MatchAdmin::class)
            ->set('selectedDisputeId', $dispute->id)
            ->set('resolution', $resolution)
            ->call('resolveDispute')
            ->assertHasNoErrors();

        self::assertSame(MatchStatus::IN_PROGRESS, $match->fresh()->status);
        self::assertSame($originalAttempt + 1, $match->fresh()->active_attempt_number);
        self::assertDatabaseHas('match_attempts', [
            'match_id' => $match->id, 'attempt_number' => $originalAttempt + 1, 'status' => 'open',
        ]);
        self::assertDatabaseHas('match_attempts', [
            'match_id' => $match->id, 'attempt_number' => $originalAttempt, 'status' => 'rematch',
        ]);

        foreach ([$first, $second] as $player) {
            $this->actingAs($player);
            $pages[$player->id]->call('$refresh')
                ->assertSee('An administrator ruled a rematch. Play again and submit a new result.')
                ->assertSee('Submit Match Results')
                ->assertDontSee('Result conflict detected')
                ->assertDontSee('id="conflict-dispute-title"', escape: false);
            Livewire::actingAs($player)->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
                ->assertSeeInOrder(['Admin Ruling', 'This is a rematch — play again', 'SUBMIT RESULTS', 'Submit Match Results'])
                ->assertDontSee('Your result has already been submitted.');
            self::assertDatabaseHas('notifications', [
                'user_id' => $player->id, 'title' => 'Rematch Required',
                'message' => 'An administrator ruled a rematch. Play again and submit a new result.',
                'action_url' => "/tournaments/{$match->tournament->uuid}/view?activeTab=submit-results&match={$match->uuid}",
            ]);
        }

        $submit->execute($match->fresh(), $first->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $second->id, MatchOutcome::LOSS);
        self::assertSame(MatchStatus::COMPLETED, $match->fresh()->status);
        Livewire::actingAs($first)->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertDontSee('An administrator ruled a rematch. Play again and submit a new result.')
            ->assertDontSee('Submit Match Results');
    }

    public function test_admin_winner_ruling_reaches_both_players_and_clears_the_embedded_conflict_page(): void
    {
        [$match, $first, $second] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $first->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $second->id, MatchOutcome::WIN);
        $page = Livewire::actingAs($second)->withQueryParams(['activeTab' => 'submit-results', 'match' => $match->uuid])
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])->assertSee('Result conflict detected');
        $resolution = $match->playerARegistration->includesUser($first->id) ? 'player_a' : 'player_b';

        Queue::fake();
        Livewire::actingAs($this->admin)->test(MatchAdmin::class)
            ->set('selectedDisputeId', $match->disputes()->firstOrFail()->id)
            ->set('resolution', $resolution)
            ->call('resolveDispute')->assertHasNoErrors();

        $message = "An administrator resolved the dispute. Winner: {$first->username}.";
        foreach ([$first, $second] as $player) {
            self::assertDatabaseHas('notifications', ['user_id' => $player->id, 'title' => 'Admin Ruling', 'message' => $message]);
        }
        $this->actingAs($second);
        $page->call('$refresh')->assertSee($message)->assertDontSee('Result conflict detected')->assertDontSee('Submit Match Results');
    }

    public function test_review_match_dispute_opens_the_result_section_and_targets_the_dispute_content(): void
    {
        [$match, $first, $second] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $first->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $second->id, MatchOutcome::WIN);

        Livewire::actingAs($first)->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee('Review Match Dispute')
            ->call('openMatch', $match->uuid)
            ->assertSet('activeTab', 'submit-results')
            ->assertDispatched('tournament-content-opened', tournamentUuid: $match->tournament->uuid, matchUuid: $match->uuid, tab: 'submit-results', focus: 'dispute');

        Livewire::actingAs($first)->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertSee('data-match-content="dispute"', escape: false);
    }

    public function test_player_tournament_header_shows_the_winners_prize_instead_of_the_combined_pool(): void
    {
        [$match, $player] = $this->activeTwoPlayerMatch(['free_prize_1st' => '15.00', 'free_prize_2nd' => '5.00']);
        $match->tournament->update([
            'financial_finalized_at' => now(),
            'finalized_first_prize' => '15.00',
            'finalized_second_prize' => '5.00',
            'finalized_gross_pool' => '20.00',
            'finalized_commission_amount' => '0.00',
        ]);
        Livewire::actingAs($player)->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee("Current Winner's Prize")
            ->assertViewHas('prizeCalculation', fn ($prizes) => (float) $prizes['distributions'][1] === 15.0 && (float) $prizes['prize_pool'] === 20.0)
            ->assertSeeInOrder(["Current Winner's Prize", '$15.00', 'Entry Fee'])
            ->assertDontSee('Current Prize Pool');
    }

    public function test_non_participant_does_not_get_the_v2_submit_result_tab(): void
    {
        [$match] = $this->activeTwoPlayerMatch();
        $outsider = $this->user('result-outsider@example.com', 'resultoutsider', 'PLAYER');

        Livewire::actingAs($outsider)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertDontSeeHtml("selectTab('submit-results')")
            ->assertDontSee('Submit Match Results');
    }

    public function test_opponent_submission_warning_and_deadline_are_visible_in_tournament_view(): void
    {
        [$match, $firstPlayer, $respondingPlayer] = $this->activeTwoPlayerMatch();
        app(SubmitV2MatchResultAction::class)->execute($match, $firstPlayer->id, MatchOutcome::WIN);

        Livewire::actingAs($respondingPlayer)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee('Your opponent submitted a result. You have 5 minutes to report your result. Submit before the timer expires to avoid an automatic loss.')
            ->assertSeeHtml('result-deadline-timer');
    }

    public function test_returning_player_self_heals_an_expired_result_timeout_and_sees_the_reason(): void
    {
        [$match, $firstPlayer, $latePlayer] = $this->activeTwoPlayerMatch();
        app(SubmitV2MatchResultAction::class)->execute($match, $firstPlayer->id, MatchOutcome::WIN);
        $this->travelTo($match->attempts()->firstOrFail()->result_deadline_at->addSecond());

        Livewire::actingAs($latePlayer)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee('You lost because you did not report your result within 5 minutes after your opponent submitted.')
            ->assertSee('Result submission is closed because the 5-minute response deadline expired.')
            ->assertDontSee('Submit Match Results');

        self::assertSame(MatchStatus::COMPLETED, $match->fresh()->status);
        self::assertSame('opponent_submission_timeout', $match->fresh()->resolution_reason);
    }

    public function test_first_submitter_is_told_why_the_result_form_is_no_longer_available(): void
    {
        [$match, $firstPlayer] = $this->activeTwoPlayerMatch();
        app(SubmitV2MatchResultAction::class)->execute($match, $firstPlayer->id, MatchOutcome::WIN);

        Livewire::actingAs($firstPlayer)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee('Waiting for opponent result')
            ->assertSee('Your result has already been submitted. Waiting for your opponent to report their result.')
            ->assertDontSee('Submit Match Results');
    }

    public function test_admin_resolved_match_explains_why_result_submission_is_closed(): void
    {
        [$match, $firstPlayer, $secondPlayer] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $firstPlayer->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $secondPlayer->id, MatchOutcome::WIN);
        $match->refresh();
        $winnerResolution = $match->playerARegistration->includesUser($firstPlayer->id)
            ? DisputeResolution::PLAYER_A
            : DisputeResolution::PLAYER_B;
        app(ResolveDisputeAction::class)->execute($match->disputes()->firstOrFail(), $this->admin, $winnerResolution);

        Livewire::actingAs($secondPlayer)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee('Result submission is closed because an admin reviewed and resolved a dispute for this match.')
            ->assertDontSee('Submit Match Results');
    }

    public static function penaltyBalanceCases(): array
    {
        return [
            'empty wallet' => ['0.00', '-10.00', '-7.00'],
            'insufficient wallet' => ['5.00', '-5.00', '-2.00'],
        ];
    }

    #[DataProvider('penaltyBalanceCases')]
    public function test_admin_penalty_can_overdraw_the_wallet_and_deposits_repay_it(string $startingBalance, string $afterPenalty, string $afterPartialDeposit): void
    {
        [$match, $first, $second] = $this->activeTwoPlayerMatch();
        $wallet = $this->walletFor($second, $startingBalance);
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $first->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $second->id, MatchOutcome::WIN);
        $dispute = $match->disputes()->firstOrFail();
        $resolution = $match->playerARegistration->includesUser($first->id) ? 'player_a' : 'player_b';

        $page = Livewire::actingAs($this->admin)->test(MatchAdmin::class)
            ->call('selectMatch', $match->id)
            ->assertSee('openDispute('.$dispute->id.')', escape: false)
            ->assertSee('disputeOpen && disputeId === '.$dispute->id, escape: false)
            ->set('selectedDisputeId', $dispute->id)
            ->set('showDisputeModal', true)
            ->set('resolution', $resolution)
            ->set('complianceUserId', (string) $second->id)
            ->set('complianceBanReason', 'The submitted evidence demonstrates a deliberately false result.')
            ->set('balancePenalty', '10.00')
            ->call('resolveDispute')
            ->assertHasNoErrors()
            ->assertDispatched('dispute-ruling-saved')
            ->assertSet('showDisputeModal', false);

        self::assertSame($afterPenalty, $wallet->fresh()->cached_balance);
        self::assertDatabaseHas('ledger_entries', ['wallet_id' => $wallet->id, 'amount' => '-10.00', 'running_balance' => $afterPenalty]);
        $strikeId = DB::table('player_dispute_strikes')->where('dispute_id', $dispute->id)->value('id');
        self::assertDatabaseHas('ledger_entries', ['wallet_id' => $wallet->id, 'reference_type' => 'player_dispute_strike', 'reference_id' => $strikeId]);
        $page->call('resolveDispute');
        self::assertSame($afterPenalty, $wallet->fresh()->cached_balance);

        Livewire::actingAs($second)->test(WalletBalance::class)
            ->assertSee('$'.$afterPenalty);
        $deposits = app(ProcessDepositAction::class);
        $deposits->execute($wallet, '3.00', 'test', 'penalty-partial');
        self::assertSame($afterPartialDeposit, $wallet->fresh()->cached_balance);
        $deposits->execute($wallet, '7.00', 'test', 'penalty-rest');
        self::assertSame($startingBalance, $wallet->fresh()->cached_balance);
        $deposits->execute($wallet, '7.00', 'test', 'penalty-rest');
        self::assertSame($startingBalance, $wallet->fresh()->cached_balance);
    }

    public function test_admin_dispute_validation_and_failures_are_visible_in_the_modal(): void
    {
        [$match, $first, $second] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $first->id, MatchOutcome::WIN);
        $submit->execute($match->fresh(), $second->id, MatchOutcome::WIN);
        $dispute = $match->disputes()->firstOrFail();
        $page = Livewire::actingAs($this->admin)->test(MatchAdmin::class)
            ->call('selectMatch', $match->id)
            ->call('openDisputeModal', $dispute->id)
            ->call('resolveDispute')
            ->assertHasErrors('resolution')
            ->assertSee('Select a match outcome before submitting the ruling.')
            ->assertSee('The ruling was not saved. Please review the following:')
            ->assertSet('showDisputeModal', true)
            ->set('resolution', 'player_a')
            ->set('balancePenalty', '10.00')
            ->call('resolveDispute')
            ->assertHasErrors('complianceUserId')
            ->assertSee('Select the player receiving the penalty.');

        $resolver = \Mockery::mock(ResolveDisputeAction::class);
        $resolver->shouldReceive('execute')->once()->andThrow(new LogicException('Dispute is already resolved.'));
        $this->app->instance(ResolveDisputeAction::class, $resolver);
        $page->set('balancePenalty', '0.00')->call('resolveDispute')
            ->assertHasErrors('disputeResolution')
            ->assertSee('Dispute is already resolved.')
            ->assertSet('showDisputeModal', true);
    }

    public function test_v2_matches_cannot_be_forfeited(): void
    {
        [$match] = $this->activeTwoPlayerMatch();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Forfeits are currently disabled for V2 matches.');

        app(ForfeitMatchAction::class)->execute($match, (int) $match->player_a_registration_id);
    }

    public function test_first_submitter_receives_conflict_modal_on_refresh_and_can_upload_evidence(): void
    {
        Storage::fake('public');
        [$match, $first, $second] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $first->id, MatchOutcome::WIN);
        $page = Livewire::actingAs($first)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertDontSee('id="conflict-dispute-title"', escape: false);

        $submit->execute($match->fresh(), $second->id, MatchOutcome::WIN);

        $page->call('$refresh')
            ->assertSee('id="conflict-dispute-title"', escape: false)
            ->set('evidenceFile', UploadedFile::fake()->image('score.png'))
            ->call('submitDisputeStatement')
            ->assertHasNoErrors()
            ->assertDontSee('id="conflict-dispute-title"', escape: false);

        $evidence = $match->disputes()->firstOrFail()->evidence()->where('uploaded_by', $first->id)->firstOrFail();
        Storage::disk('public')->assertExists($evidence->file_path);
        $page->call('submitDisputeStatement')->assertHasErrors('evidenceFile');
        self::assertDatabaseCount('match_evidence', 1);
    }

    public function test_conflicting_v2_results_create_a_dispute_for_admin_review(): void
    {
        Storage::fake('public');
        [$match, $p1, $p2] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $p1->id, MatchOutcome::WIN);
        Livewire::actingAs($p2)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->set('resultOutcome', 'win')
            ->call('submitResult')
            ->assertHasNoErrors()
            ->assertSee('Result conflict detected')
            ->assertSee('Submit dispute details');

        self::assertSame(MatchStatus::DISPUTED, $match->fresh()->status);
        self::assertDatabaseHas('match_disputes', [
            'match_id' => $match->id,
            'opened_by' => $p2->id,
            'reason' => 'V2 result submissions conflict.',
        ]);

        Livewire::actingAs($p1)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertSee('Result conflict detected')
            ->assertSee('Submit dispute details')
            ->assertSee('id="conflict-dispute-title"', escape: false)
            ->set('disputeReason', 'I won the match and want the admin to review the final score.')
            ->call('submitDisputeStatement')
            ->assertHasErrors(['evidenceFile' => 'required'])
            ->assertSee('Please upload a screenshot before submitting your dispute.')
            ->assertDontSee('Unable to submit your dispute details.')
            ->set('evidenceFile', UploadedFile::fake()->image('first-score.png'))
            ->call('submitDisputeStatement')
            ->assertHasNoErrors()
            ->assertSee('Your dispute response has been submitted.')
            ->assertDontSee('id="conflict-dispute-title"', escape: false);
        self::assertDatabaseHas('match_evidence', [
            'uploaded_by' => $p1->id,
            'reason' => 'I won the match and want the admin to review the final score.',
        ]);

        Livewire::actingAs($p2)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertSee('Submit dispute details')
            ->assertDontSee('Disputes (')
            ->call('submitDisputeStatement')
            ->assertHasErrors(['evidenceFile' => 'required'])
            ->assertSee('Please upload a screenshot before submitting your dispute.')
            ->set('evidenceFile', UploadedFile::fake()->image('second-score.png'))
            ->call('submitDisputeStatement')
            ->assertHasNoErrors()
            ->assertSee('Your dispute response has been submitted.');

        self::assertDatabaseCount('match_evidence', 2);
        self::assertDatabaseHas('match_evidence', [
            'uploaded_by' => $p2->id,
            'reason' => null,
        ]);
        foreach ($match->disputes()->firstOrFail()->evidence as $evidence) {
            self::assertNotNull($evidence->file_path);
            Storage::disk('public')->assertExists($evidence->file_path);
        }
    }

    public function test_v2_results_submit_directly_without_a_confirmation_modal(): void
    {
        [$match, $firstPlayer, $secondPlayer] = $this->activeTwoPlayerMatch();

        Livewire::actingAs($firstPlayer)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertSee('wire:submit.prevent="submitResult"', escape: false)
            ->assertDontSee('Confirm your result')
            ->assertDontSee('Open Official Dispute')
            ->call('submitResult')
            ->assertHasErrors(['resultOutcome' => 'required'])
            ->set('resultOutcome', 'win')
            ->call('submitResult')
            ->assertHasNoErrors();

        self::assertSame(MatchStatus::WAITING_FOR_CONFIRMATION, $match->fresh()->status);

        Livewire::actingAs($secondPlayer)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->set('resultOutcome', 'win')
            ->assertDontSee('Confirm your result')
            ->assertDontSee('Open Official Dispute')
            ->call('submitResult')
            ->assertHasNoErrors()
            ->assertSee('Result conflict detected');

        self::assertSame(MatchStatus::DISPUTED, $match->fresh()->status);
    }

    public function test_tournament_overview_shows_next_match_champion_and_second_place_outcomes(): void
    {
        [$match, $winner, $runnerUp] = $this->activeTwoPlayerMatch(['free_prize_2nd' => '5.00']);
        $winnerRegistrationId = (int) $match->tournament->registrations()->where('user_id', $winner->id)->value('id');
        $runnerUpRegistrationId = (int) $match->tournament->registrations()->where('user_id', $runnerUp->id)->value('id');

        $match->forceFill([
            'winner_registration_id' => $winnerRegistrationId,
            'status' => MatchStatus::COMPLETED,
            'completed_at' => now(),
        ])->save();

        $nextRound = $match->round->bracket->rounds()->create(['round_number' => 2]);
        $nextMatch = GameMatch::query()->create([
            'uuid' => Str::uuid()->toString(),
            'tournament_id' => $match->tournament_id,
            'round_id' => $nextRound->id,
            'player_a_registration_id' => $winnerRegistrationId,
            'status' => MatchStatus::PENDING,
        ]);
        self::assertSame(2, (int) $match->tournament->rounds()->max('round_number'));

        Livewire::actingAs($winner)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertViewHas('awaitingNextMatch', true)
            ->assertSee('Winner · Waiting for next match')
            ->assertDontSee('Reservation Confirmed');

        $nextMatch->delete();
        $nextRound->delete();
        DB::table('tournaments')->where('id', $match->tournament_id)->update([
            'prize_2nd' => '5.00',
            'finalized_second_prize' => '5.00',
        ]);
        $match->forceFill([
            'player_a_registration_id' => $winnerRegistrationId,
            'player_b_registration_id' => $runnerUpRegistrationId,
            'winner_registration_id' => $winnerRegistrationId,
        ])->save();
        Livewire::actingAs($winner)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee('Champion')
            ->assertDontSee('Reservation Confirmed');

        Livewire::actingAs($runnerUp)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee('Second Place')
            ->assertSee('$5.00')
            ->assertDontSee('Reservation Confirmed');
    }

    #[DataProvider('conflictingResultPairs')]
    public function test_every_result_pair_other_than_win_loss_or_two_draws_creates_a_dispute(string $first, string $second): void
    {
        [$match, $p1, $p2] = $this->activeTwoPlayerMatch();
        $submit = app(SubmitV2MatchResultAction::class);
        $submit->execute($match, $p1->id, MatchOutcome::from($first));
        $submit->execute($match->fresh(), $p2->id, MatchOutcome::from($second));

        self::assertSame(MatchStatus::DISPUTED, $match->fresh()->status);
        self::assertSame('conflicting_submissions', $match->fresh()->resolution_reason);
        self::assertSame(1, $match->disputes()->count());
    }

    public static function conflictingResultPairs(): array
    {
        return [
            'win and win' => ['win', 'win'],
            'loss and loss' => ['loss', 'loss'],
            'win and draw' => ['win', 'draw'],
            'draw and win' => ['draw', 'win'],
            'loss and draw' => ['loss', 'draw'],
            'draw and loss' => ['draw', 'loss'],
        ];
    }

    public function test_first_submitter_wins_after_five_minute_non_response_even_when_reporting_loss(): void
    {
        [$match, $p1, $p2] = $this->activeTwoPlayerMatch();
        app(SubmitV2MatchResultAction::class)->execute($match, $p1->id, MatchOutcome::LOSS);
        $this->travelTo(now()->addMinutes(5)->addSecond());

        (new ResolveV2ResultTimeoutsJob)->handle(app(ResolveV2ResultTimeoutAction::class));

        self::assertSame(MatchStatus::COMPLETED, $match->fresh()->status);
        self::assertSame($match->playerARegistration->includesUser($p1->id)
            ? $match->player_a_registration_id
            : $match->player_b_registration_id, $match->fresh()->winner_registration_id);
        self::assertSame('opponent_submission_timeout', $match->fresh()->resolution_reason);

        Livewire::actingAs($p2)
            ->test(TournamentDetail::class, ['uuid' => $match->tournament->uuid])
            ->assertSee('You lost because you did not report your result within 5 minutes after your opponent submitted.');
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

    public function test_paid_v2_join_shows_insufficient_balance_without_registering_or_charging(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $template = $this->template('daily', 8, '23:59');
        $template->update(['entry_fee' => '50.00']);
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);

        foreach (['0.00', '10.00'] as $index => $balance) {
            $player = $this->user("v2-insufficient{$index}@example.com", "v2insufficient{$index}", 'PLAYER');
            $wallet = $this->walletFor($player, $balance);

            Livewire::actingAs($player)
                ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
                ->set('gameIdValue', "V2InsufficientPlayer{$index}")
                ->call('register')
                ->assertHasNoErrors()
                ->assertSee('Insufficient balance. Please top up your wallet to pay the entrance fee.')
                ->assertDontSee('Unable to register for this tournament.')
                ->assertDontSee('Successfully joined the tournament!');

            self::assertSame($balance, $wallet->fresh()->cached_balance);
        }

        $this->assertDatabaseCount('tournament_registrations', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertDatabaseCount('error_incidents', 0);
    }

    public function test_player_cancellation_dialog_closes_after_a_v2_request_is_created(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $template = $this->template('daily', 8, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $player = $this->user('dialog-cancel@example.com', 'dialogcancel', 'PLAYER');
        $opponent = $this->user('dialog-opponent@example.com', 'dialogopponent', 'PLAYER');
        $register = app(RegisterForV2TournamentAction::class);
        $registration = $register->execute($tournament->fresh(), $player, null, 'dialogplayer');
        $register->execute($tournament->fresh(), $opponent, null, 'dialogopponent');
        Queue::fake();

        Livewire::actingAs($player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSee('Cancel Registration')
            ->assertSee('open-registration-cancellation')
            ->assertSee('Cancel Registration?')
            ->call('cancelRegistration')
            ->assertSet('cancellationError', '')
            ->assertDispatched('registration-cancellation-completed');

        $request = $tournament->cancellationRequests()->firstOrFail();
        self::assertSame('approved', $request->status);
        self::assertSame('cancelled', $registration->fresh()->status->value);
        Queue::assertNotPushed(NotifyV2CancellationVotersJob::class);
    }

    public function test_v2_cancellation_cutoff_is_consistent_at_and_inside_thirty_minutes(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $player = $this->user('cutoff-cancel@example.com', 'cutoffcancel', 'PLAYER');
        $atCutoffTemplate = $this->template('daily', 4, '10:30');
        $atCutoffTournament = app(MaterializeV2OccurrenceAction::class)->execute($atCutoffTemplate->scheduleSlots->first(), $this->admin);
        $atCutoffRegistration = app(RegisterForV2TournamentAction::class)->execute($atCutoffTournament, $player, null, 'cutoff-player');

        Livewire::actingAs($player)
            ->test(TournamentDetail::class, ['uuid' => $atCutoffTournament->uuid])
            ->assertViewHas('canCancelRegistration', true)
            ->assertSee('Cancel Registration')
            ->call('cancelRegistration')
            ->assertSet('cancellationError', '');
        self::assertSame('cancelled', $atCutoffRegistration->fresh()->status->value);

        $insidePlayer = $this->user('closed-cancel@example.com', 'closedcancel', 'PLAYER');
        $insideTemplate = $this->template('daily', 4, '10:29');
        $insideTournament = app(MaterializeV2OccurrenceAction::class)->execute($insideTemplate->scheduleSlots->first(), $this->admin);
        $insideRegistration = app(RegisterForV2TournamentAction::class)->execute($insideTournament, $insidePlayer, null, 'closed-player');

        Livewire::actingAs($insidePlayer)
            ->test(TournamentDetail::class, ['uuid' => $insideTournament->uuid])
            ->assertViewHas('canCancelRegistration', true)
            ->assertSee('Cancel Registration')
            ->call('cancelRegistration')
            ->assertSet('cancellationError', '')
            ->assertDispatched('registration-cancellation-completed');
        self::assertSame('cancelled', $insideRegistration->fresh()->status->value);
    }

    public static function cancellationRefundCases(): array
    {
        return [
            'over thirty minutes' => [1801, '10.00', '10.00', '0.00'],
            'exactly thirty minutes' => [1800, '10.00', '10.00', '0.00'],
            'under thirty minutes' => [1799, '10.00', '9.00', '1.00'],
            'one second before start' => [1, '10.00', '9.00', '1.00'],
            'cent rounding' => [60, '10.05', '9.04', '1.01'],
        ];
    }

    #[DataProvider('cancellationRefundCases')]
    public function test_paid_cancellation_refunds_and_accounts_for_the_late_fee(int $seconds, string $entry, string $refund, string $fee): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $template = $this->template('daily', 4, '11:00');
        $template->update(['entry_fee' => $entry]);
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $player = $this->user('fee-cancel@example.com', 'feecancel', 'PLAYER');
        $wallet = $this->walletFor($player, $entry);
        $platformWallet = User::where('email', 'platform@playersaloons.com')->firstOrFail()->wallet;
        $platformBefore = DecimalMoney::toMinor($platformWallet->cached_balance);
        $registration = app(RegisterForV2TournamentAction::class)->execute($tournament, $player, null, 'fee-player');
        $this->travelTo($tournament->start_at->copy()->subSeconds($seconds));

        $page = Livewire::actingAs($player)->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertViewHas('canCancelRegistration', true)
            ->assertSee("Cancellation fee: $$fee. Refund: $$refund.")
            ->assertSee('10% of the entry fee')
            ->call('cancelRegistration')
            ->assertSet('cancellationError', '')
            ->assertDispatched('registration-cancellation-completed')
            ->assertDispatched('wallet-balance-updated');

        self::assertSame($refund, $wallet->fresh()->cached_balance);
        self::assertSame($platformBefore + DecimalMoney::toMinor($fee), DecimalMoney::toMinor($platformWallet->fresh()->cached_balance));
        self::assertDatabaseHas('refunds', ['registration_id' => $registration->id, 'amount' => $refund]);
        self::assertDatabaseHas('ledger_entries', ['wallet_id' => $wallet->id, 'type' => 'REFUND', 'amount' => $refund]);
        self::assertSame('refunded', $registration->fresh()->payment_status->value);
        $page->call('cancelRegistration');
        self::assertSame($refund, $wallet->fresh()->cached_balance);
        self::assertDatabaseCount('refunds', 1);
    }

    public function test_cancellation_is_rejected_at_the_exact_start_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC'));
        $template = $this->template('daily', 4, '11:00');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $player = $this->user('start-cancel@example.com', 'startcancel', 'PLAYER');
        $registration = app(RegisterForV2TournamentAction::class)->execute($tournament, $player, null, 'start');
        $this->travelTo($tournament->start_at);
        Livewire::actingAs($player)->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertViewHas('canCancelRegistration', false)
            ->call('cancelRegistration')
            ->assertSet('cancellationError', 'Cancellation is available only before the tournament starts.');
        self::assertSame('confirmed', $registration->fresh()->status->value);
        self::assertDatabaseCount('refunds', 0);
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
        // Previously created pending requests can still finish their approval flow.
        $request = TournamentCancellationRequest::query()->create([
            'uuid' => Str::uuid(), 'tournament_id' => $tournament->id,
            'registration_id' => $registrations[0]->id, 'requested_by' => $players[0]->id,
            'status' => 'pending', 'eligible_voter_count' => 3,
            'eligible_voter_ids' => [$players[1]->id, $players[2]->id, $players[3]->id],
            'required_approvals' => 2, 'requested_at' => now(), 'expires_at' => $tournament->start_at,
        ]);

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

    public function test_player_can_join_multiple_slots_in_the_same_recurrence_period(): void
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

        $firstRegistration = app(RegisterForV2TournamentAction::class)->execute($first, $player, null, 'one-slot-a');
        $secondRegistration = app(RegisterForV2TournamentAction::class)->execute($second, $player, null, 'one-slot-b');

        self::assertNotSame($firstRegistration->tournament_id, $secondRegistration->tournament_id);
        self::assertSame(2, TournamentRegistration::query()->where('user_id', $player->id)->count());
    }

    public function test_unresolved_final_stays_ongoing_until_admin_resolves_a_champion_after_24_hours(): void
    {
        [$match, $p1, $p2] = $this->activeTwoPlayerMatch();
        $tournament = $match->tournament->fresh();
        Tournament::query()->whereKey($tournament->id)->update([
            'end_at' => now()->subDay()->subSecond(),
            'prize_funding_mode' => 'entry_fees',
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

        app(V2StalledMatchService::class)->escalateUnresolvedFinal($held->id);
        $dispute = MatchDispute::query()->where('match_id', $held->id)->firstOrFail();
        self::assertSame(MatchStatus::DISPUTED, $held->fresh()->status);

        app(ResolveDisputeAction::class)->execute($dispute, $this->admin, DisputeResolution::PLAYER_A);
        self::assertSame(MatchStatus::COMPLETED, $held->fresh()->status);
        app(AwardV2PrizesAction::class)->execute($tournament->fresh());
        app(AwardV2PrizesAction::class)->execute($tournament->fresh());

        $settled = $tournament->fresh();
        self::assertSame(TournamentStatus::COMPLETED, $settled->status);
        self::assertNull($settled->completion_reason);
        self::assertSame('paid', $settled->payout_status);
        self::assertSame('1.50', $settled->finalized_commission_amount);
        self::assertSame('8.49', $settled->finalized_first_prize);
        self::assertSame('0.00', $settled->finalized_second_prize);
        $playerAUserId = $held->playerARegistration->user_id;
        self::assertSame('8.49', User::query()->findOrFail($playerAUserId)->wallet->cached_balance);
        $playerBUserId = $held->playerBRegistration->user_id;
        self::assertSame('0.00', User::query()->findOrFail($playerBUserId)->wallet->cached_balance);
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
        $this->assertDatabaseHas('tournament_registrations', ['user_id' => $player->id, 'platform_id' => $console->id, 'game_id_value' => 'XboxHandle']);
        $this->assertDatabaseMissing('user_game_accounts', ['user_id' => $player->id, 'platform_id' => $console->id]);
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

    #[DataProvider('netherlandsScheduleDates')]
    public function test_admin_creation_and_added_slots_store_netherlands_schedules_as_utc(string $date, string $utcStart, string $utcAddedStart): void
    {
        $this->travelTo(CarbonImmutable::parse($date.' 10:00:00', 'UTC'));
        DB::table('system_settings')->where('key', 'tournament.timezone')->update(['value' => 'Europe/Amsterdam']);

        $this->actingAs($this->admin)->post(
            route('admin.tournaments.v2.store'),
            $this->multiPlatformPayload([$this->platform->id]),
        )->assertSessionHasNoErrors()->assertRedirect();

        $tournament = Tournament::query()->firstOrFail();
        $template = $tournament->template;
        $slot = $template->scheduleSlots()->firstOrFail();
        self::assertSame($date.' '.$utcStart, $slot->schedule_start_at->utc()->format('Y-m-d H:i:s'));
        self::assertSame($date.' '.$utcStart, $tournament->start_at->utc()->format('Y-m-d H:i:s'));
        self::assertSame('23:00', $tournament->start_at->copy()->setTimezone('Europe/Amsterdam')->format('H:i'));
        self::assertSame('23:59', $slot->schedule_end_at->copy()->setTimezone('Europe/Amsterdam')->format('H:i'));

        $this->post(route('admin.tournaments.v2.templates.slots.store', $template), [
            'schedule_start_at' => $date.'T22:00',
            'schedule_end_at' => now()->addDay()->format('Y-m-d').'T23:59',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $addedSlot = $template->scheduleSlots()->reorder()->latest('id')->firstOrFail();
        $addedOccurrence = $addedSlot->occurrences()->firstOrFail();
        self::assertSame($date.' '.$utcAddedStart, $addedSlot->schedule_start_at->utc()->format('Y-m-d H:i:s'));
        self::assertSame($date.' '.$utcAddedStart, $addedOccurrence->start_at->utc()->format('Y-m-d H:i:s'));
        self::assertSame('23:59', $addedSlot->schedule_end_at->copy()->setTimezone('Europe/Amsterdam')->format('H:i'));
    }

    #[DataProvider('netherlandsScheduleDates')]
    public function test_existing_utc_schedules_move_to_the_same_wall_clock_time_in_netherlands(string $date, string $utcStart, string $utcAddedStart): void
    {
        $this->travelTo(CarbonImmutable::parse($date.' 10:00:00', 'UTC'));
        DB::table('system_settings')->where('key', 'tournament.timezone')->update(['value' => 'UTC']);
        $this->actingAs($this->admin)->post(
            route('admin.tournaments.v2.store'),
            $this->multiPlatformPayload([$this->platform->id]),
        )->assertSessionHasNoErrors();

        $tournament = Tournament::query()->firstOrFail();
        $template = $tournament->template;
        $template->update(['next_run_at' => now()->subDay()->setTime(23, 0)]);
        $convert = app(ConvertTournamentSchedulesTimezoneAction::class);
        $convert->execute('UTC', 'Europe/Amsterdam');
        $convert->execute('UTC', 'Europe/Amsterdam');

        $tournament->refresh();
        $slot = $template->scheduleSlots()->firstOrFail();
        self::assertSame('Europe/Amsterdam', $tournament->timezone);
        self::assertSame($date.' '.$utcStart, $tournament->start_at->utc()->format('Y-m-d H:i:s'));
        self::assertSame($date.' '.$utcStart, $slot->schedule_start_at->utc()->format('Y-m-d H:i:s'));
        self::assertSame('23:00', $tournament->start_at->copy()->setTimezone('Europe/Amsterdam')->format('H:i'));
        self::assertSame('23:59', $slot->schedule_end_at->copy()->setTimezone('Europe/Amsterdam')->format('H:i'));
        self::assertSame('23:00', $template->fresh()->next_run_at->setTimezone('Europe/Amsterdam')->format('H:i'));
    }

    public static function netherlandsScheduleDates(): array
    {
        return [
            'summer time' => ['2026-09-12', '21:00:00', '20:00:00'],
            'winter time' => ['2026-12-12', '22:00:00', '21:00:00'],
        ];
    }

    private function multiPlatformPayload(array $ids, string $type = 'tournament'): array
    {
        return [
            'name' => 'Multiple Platforms '.$type, 'competition_type' => $type,
            'game_id' => $this->game->id, 'platform_ids' => $ids,
            'frequency' => 'daily', 'max_teams' => 4, 'entry_fee' => '0.00',
            ...($type === 'tournament' ? ['free_prize_1st' => '0.00', 'free_prize_2nd' => '0.00'] : []),
            'winning_points' => 15, 'waiting_result_time' => 5,
            'full_first_percent' => 90, 'full_second_percent' => 0,
            'slots' => [[
                'local_start_time' => '23:00',
                'schedule_start_at' => now()->format('Y-m-d').'T23:00',
                'schedule_end_at' => now()->addDay()->format('Y-m-d').'T23:59',
            ]],
        ];
    }

    public function test_existing_manual_ready_preferences_still_start_automatically(): void
    {
        $template = $this->template('daily', 4, '23:59');
        $tournament = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $p1 = $this->user('manual-ready-1@example.com', 'manualready1', 'PLAYER');
        $p2 = $this->user('manual-ready-2@example.com', 'manualready2', 'PLAYER');
        $register = app(RegisterForV2TournamentAction::class);
        $register->execute($tournament, $p1, null, 'manual-1', 'confirm_each_match');
        $register->execute($tournament->fresh(), $p2, null, 'manual-2', 'confirm_each_match');

        $this->travelTo($tournament->start_at->copy()->addSecond());
        app(V2TournamentLifecycle::class)->reconcile($tournament->fresh());

        $match = GameMatch::query()->where('tournament_id', $tournament->id)->firstOrFail();
        self::assertSame(MatchStatus::IN_PROGRESS, $match->status);
        self::assertNotNull($match->player_a_ready_at);
        self::assertNotNull($match->player_b_ready_at);

        Livewire::actingAs($p1)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertDontSee("I'm Here")
            ->assertSee('Submit Match Results');

        Livewire::actingAs($p2)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertDontSee("I'm Here")
            ->assertSee('Submit Match Results');
    }

    private function activeTwoPlayerMatch(array $slotOverrides = [], CompetitionType $type = CompetitionType::TOURNAMENT): array
    {
        $template = $this->template('daily', $type === CompetitionType::HEAD_TO_HEAD ? 2 : 4, '23:59', $slotOverrides, $type);
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

    public function test_disabled_and_deleted_games_hide_v2_discovery_and_admin_groups(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-12 10:00:00', 'UTC'));
        $template = $this->template('daily', 4, '23:59');
        $occurrence = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->firstOrFail(), $this->admin);
        $discovery = app(V2TournamentDiscoveryService::class);
        self::assertSame(1, $discovery->paginate('upcoming', [])->total());

        $this->game->update(['is_active' => false]);
        self::assertSame(0, $discovery->paginate('upcoming', ['game_id' => (string) $this->game->id])->total());
        Livewire::actingAs($this->admin)->test(TournamentAdmin::class)
            ->assertViewHas('v2Templates', fn ($items) => $items->total() === 0)
            ->assertViewHas('countAll', 0);
        $template->update(['competition_type' => CompetitionType::HEAD_TO_HEAD, 'name' => 'Unavailable H2H Schedule']);
        $occurrence->update(['competition_type' => CompetitionType::HEAD_TO_HEAD]);
        $this->actingAs($this->admin)->get(route('admin.h2h.index'))
            ->assertOk()
            ->assertDontSee('Unavailable H2H Schedule');

        $this->game->update(['is_active' => true]);
        self::assertSame(1, $discovery->paginate('upcoming', ['competition_type' => 'head_to_head'])->total());
        $this->game->delete();
        self::assertSame(0, $discovery->paginate('upcoming', [])->total());
        $occurrence->update(['status' => TournamentStatus::COMPLETED]);
        self::assertSame(0, $discovery->paginate('past', [])->total());
        self::assertSame($this->game->id, $occurrence->fresh()->game->id);
        $this->assertDatabaseHas('tournaments', ['id' => $occurrence->id]);
    }

    private function template(string $frequency, int $maximum, string $time, array $slot = [], CompetitionType $type = CompetitionType::TOURNAMENT)
    {
        return app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'V2 Tournament',
            'competition_type' => $type->value,
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
