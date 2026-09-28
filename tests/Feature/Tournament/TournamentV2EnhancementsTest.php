<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTournamentDefault;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Actions\CancelTournamentAction;
use App\Modules\Tournament\Actions\CreateV2TournamentTemplateAction;
use App\Modules\Tournament\Actions\MaterializeV2OccurrenceAction;
use App\Modules\Tournament\Actions\PurgeEmptyV2OccurrencesAction;
use App\Modules\Tournament\Actions\RegisterForV2TournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\CancelledOccurrenceRetention;
use App\Modules\Tournament\Services\CompetitionBannerResolver;
use App\Modules\Tournament\Services\SponsoredPrizeFundingService;
use App\Modules\Tournament\Services\V2TournamentLifecycle;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\LedgerType;
use Carbon\CarbonImmutable;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TournamentV2EnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Game $game;

    private Platform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('features.tournament_v2.enabled', true);
        $this->seed([RolesAndPermissionsSeeder::class, PlatformSystemUserSeeder::class]);
        $this->admin = User::query()->create([
            'uuid' => Str::uuid(), 'email' => 'enhancements-admin@example.com', 'username' => 'enhancementsadmin',
            'password' => bcrypt('password'), 'email_verified_at' => now(), 'status' => 'active',
        ]);
        $this->admin->assignRole('SUPER_ADMIN');
        $this->game = Game::query()->create([
            'uuid' => Str::uuid(), 'slug' => 'enhancement-game', 'banner_path' => 'games/cover.webp', 'is_active' => true,
        ]);
        $this->platform = Platform::query()->create(['name' => 'PC', 'slug' => 'enhancement-pc', 'is_active' => true]);
        $this->game->platforms()->attach($this->platform);
    }

    public function test_banner_resolver_uses_shared_precedence_and_empty_state(): void
    {
        $resolver = app(CompetitionBannerResolver::class);
        self::assertSame('/storage/games/cover.webp', $resolver->resolve($this->game, CompetitionType::TOURNAMENT));

        GameTournamentDefault::query()->create([
            'game_id' => $this->game->id,
            'default_platform_id' => $this->platform->id,
            'tournament_banner_path' => 'games/default.webp',
        ]);
        $this->game->unsetRelation('tournamentDefaults');
        self::assertSame('/storage/games/default.webp', $resolver->resolve($this->game, CompetitionType::TOURNAMENT));
        self::assertSame('/storage/schedules/custom.webp', $resolver->resolve($this->game, CompetitionType::TOURNAMENT, null, 'schedules/custom.webp'));
        self::assertSame('/storage/slots/custom.webp', $resolver->resolve($this->game, CompetitionType::TOURNAMENT, 'slots/custom.webp', 'schedules/custom.webp'));

        $empty = Game::query()->create(['uuid' => Str::uuid(), 'slug' => 'empty-art', 'is_active' => true]);
        self::assertNull($resolver->resolve($empty, CompetitionType::TOURNAMENT));
    }

    public function test_sponsored_prize_reservation_is_idempotent_featured_is_per_slot_and_cancellation_releases(): void
    {
        $this->platformWallet()->update(['cached_balance' => '100.00']);
        $now = CarbonImmutable::parse('2026-09-27 10:00:00', 'UTC');
        $this->travelTo($now);
        $template = app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'Sponsored Cup',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'max_teams' => 8,
            'entry_fee' => '0.00',
            'free_prize_1st' => '10.00',
            'free_prize_2nd' => '5.00',
            'full_first_bps' => 7500,
            'full_second_bps' => 1500,
            'slots' => [
                ['label' => 'Featured', 'local_start_time' => '12:00', 'overrides' => ['is_featured' => true, 'free_prize_2nd' => '7.00']],
                ['label' => 'Standard', 'local_start_time' => '13:00'],
            ],
        ]);

        $first = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots[0], $this->admin, $now);
        self::assertNotNull($first);
        self::assertTrue($first->is_featured);
        self::assertSame('sponsored', $first->prize_funding_mode);
        self::assertSame('reserved', $first->funding_state);
        self::assertSame('17.00', $first->reserved_prize_amount);
        self::assertSame('83.00', $this->platformWallet()->fresh()->cached_balance);

        $repeated = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots[0], $this->admin, $now);
        self::assertSame($first->id, $repeated->id);
        self::assertSame(1, DB::table('ledger_entries')->where('type', LedgerType::SPONSORED_PRIZE_RESERVE->value)->count());

        $second = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots[1], $this->admin, $now);
        self::assertFalse($second->is_featured);
        self::assertSame('68.00', $this->platformWallet()->fresh()->cached_balance);

        app(SponsoredPrizeFundingService::class)->adjust($first, '12.00', '8.00');
        self::assertSame('20.00', $first->fresh()->reserved_prize_amount);
        self::assertSame('65.00', $this->platformWallet()->fresh()->cached_balance);

        app(CancelTournamentAction::class)->execute($first, $this->admin, 'admin_cancelled');
        self::assertSame('released', $first->fresh()->funding_state);
        self::assertSame('85.00', $this->platformWallet()->fresh()->cached_balance);
    }

    public function test_sponsored_prizes_materialize_as_a_platform_payable_without_treasury_cash(): void
    {
        $template = app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'Unfunded Cup',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'max_teams' => 4,
            'entry_fee' => '0.00',
            'free_prize_1st' => '25.00',
            'free_prize_2nd' => '0.00',
            'full_first_bps' => 9000,
            'full_second_bps' => 0,
            'slots' => [['local_start_time' => '23:59']],
        ]);

        $occurrence = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        self::assertNotNull($occurrence);
        self::assertSame('reserved', $occurrence->funding_state);
        self::assertSame('25.00', $occurrence->reserved_prize_amount);
        self::assertSame('-25.00', $this->platformWallet()->fresh()->cached_balance);
        $this->assertDatabaseMissing('error_incidents', ['source' => 'tournament_materializer']);
    }

    public function test_admin_request_accepts_free_sponsored_prizes_and_rejects_them_for_paid_tournaments(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-27 10:00:00', 'UTC'));
        $this->platformWallet()->update(['cached_balance' => '100.00']);
        $payload = [
            'competition_type' => 'tournament',
            'game_id' => $this->game->id,
            'platform_ids' => [$this->platform->id],
            'name' => 'Admin Sponsored Cup',
            'frequency' => 'one_time',
            'max_teams' => 4,
            'entry_fee' => '0.00',
            'free_prize_1st' => '7.00',
            'free_prize_2nd' => '1.00',
            'winning_points' => 15,
            'waiting_result_time' => 5,
            'full_first_percent' => 90,
            'full_second_percent' => 0,
            'slots' => [[
                'label' => 'Featured final',
                'local_start_time' => '12:00',
                'schedule_start_at' => '2026-09-27T12:00',
                'schedule_end_at' => '2026-09-27T23:59',
                'is_featured' => 1,
            ]],
        ];

        $this->actingAs($this->admin)->post(route('admin.tournaments.v2.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.tournaments'));
        $occurrence = Tournament::query()->where('name', 'like', 'Admin Sponsored Cup%')->firstOrFail();
        self::assertSame('8.00', $occurrence->reserved_prize_amount);
        self::assertTrue($occurrence->is_featured);
        $slot = $occurrence->scheduleSlot;
        $this->actingAs($this->admin)->put(route('admin.tournaments.v2.templates.slots.update', [$occurrence->template_id, $slot]), [
            'free_prize_1st' => '9.00',
            'free_prize_2nd' => '2.00',
        ])->assertSessionHasNoErrors();
        self::assertFalse((bool) $slot->fresh()->overrides_json['is_featured']);
        self::assertSame('9.00', $slot->fresh()->overrides_json['free_prize_1st']);
        self::assertTrue($occurrence->fresh()->is_featured);

        $this->actingAs($this->admin)->post(route('admin.tournaments.v2.store'), [
            ...$payload,
            'name' => 'Invalid Paid Cup',
            'entry_fee' => '5.00',
        ])->assertSessionHasErrors(['free_prize_1st', 'free_prize_2nd']);
    }

    public function test_retention_boundary_uses_recurrence_period_in_tournament_timezone(): void
    {
        $occurrence = new Tournament([
            'timezone' => 'Asia/Singapore',
            'frequency' => 'daily',
            'start_at' => '2026-09-27 12:00:00',
            'end_at' => '2026-09-27 13:00:00',
        ]);
        $eligible = app(CancelledOccurrenceRetention::class)->eligibleAt($occurrence);

        self::assertSame('2026-09-28 15:59:59', $eligible->format('Y-m-d H:i:s'));
    }

    public function test_free_underfilled_cancellation_is_archived_after_retention_boundary(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-25 10:00:00', 'UTC'));
        $template = app(CreateV2TournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'platform_id' => $this->platform->id,
            'name' => 'Archive Cup',
            'frequency' => 'daily',
            'timezone' => 'UTC',
            'max_teams' => 4,
            'entry_fee' => '0.00',
            'free_prize_1st' => '0.00',
            'free_prize_2nd' => '0.00',
            'full_first_bps' => 9000,
            'full_second_bps' => 0,
            'slots' => [['local_start_time' => '12:00']],
        ]);
        $occurrence = app(MaterializeV2OccurrenceAction::class)->execute($template->scheduleSlots->first(), $this->admin);
        $player = User::query()->create([
            'uuid' => Str::uuid(), 'email' => 'archive-player@example.com', 'username' => 'archiveplayer',
            'password' => bcrypt('password'), 'email_verified_at' => now(), 'status' => 'active',
        ]);
        $player->assignRole('PLAYER');
        app(RegisterForV2TournamentAction::class)->execute($occurrence, $player, null, 'ArchiveHandle');

        $this->travelTo($occurrence->start_at->copy()->addSecond());
        app(V2TournamentLifecycle::class)->reconcile($occurrence->fresh());
        $this->travelTo(CarbonImmutable::parse('2026-09-27 00:00:00', 'UTC'));

        self::assertSame(1, app(PurgeEmptyV2OccurrencesAction::class)->execute());
        self::assertNull(Tournament::query()->find($occurrence->id));
        self::assertNotNull(Tournament::withTrashed()->find($occurrence->id));
    }

    private function platformWallet(): Wallet
    {
        return Wallet::query()->whereHas('user', fn ($query) => $query->where('email', 'platform@playersaloons.com'))->firstOrFail();
    }
}
