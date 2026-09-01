<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTournamentDefault;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\ResolveDisputeAction;
use App\Modules\Match\Actions\SubmitV2MatchResultAction;
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
use App\Modules\Tournament\Actions\VoteOnV2CancellationAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\V2TournamentLifecycle;
use App\Modules\Wallet\Models\Wallet;
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
use Illuminate\Support\Str;
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

        (new ResolveV2ResultTimeoutsJob)->handle(app(SubmitV2MatchResultAction::class));

        self::assertSame(MatchStatus::COMPLETED, $match->fresh()->status);
        self::assertSame($match->playerARegistration->includesUser($p1->id)
            ? $match->player_a_registration_id
            : $match->player_b_registration_id, $match->fresh()->winner_registration_id);
        self::assertSame('opponent_submission_timeout', $match->fresh()->resolution_reason);
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

        $this->expectException(\LogicException::class);
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

        $this->expectException(\LogicException::class);
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
