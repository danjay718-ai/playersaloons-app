<?php

declare(strict_types=1);

namespace Tests\Feature\Match;

use App\Livewire\Landing\LandingPage;
use App\Livewire\Match\MatchDetail;
use App\Livewire\Tournament\PublicTournamentList;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameCategory;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\ExternalProviderAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\CreatePlayStrategyMatchGameAction;
use App\Modules\Match\Actions\OverrideMatchResultAction;
use App\Modules\Match\Actions\ReconcilePlayStrategyMatchGameAction;
use App\Modules\Match\Actions\ResolveV2ResultTimeoutAction;
use App\Modules\Match\Actions\SubmitMatchResultAction;
use App\Modules\Match\Actions\SubmitV2MatchResultAction;
use App\Modules\Match\Actions\SyncPlayStrategyMatchResultAction;
use App\Modules\Match\Jobs\CreatePlayStrategyMatchGameJob;
use App\Modules\Match\Jobs\ReconcilePlayStrategyMatchGameJob;
use App\Modules\Match\Models\ExternalMatchGame;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchAttempt;
use App\Modules\Tournament\Actions\RegisterForV2TournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\MatchOutcome;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use Database\Seeders\LandingPageSeeder;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\PlayStrategyGamesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class PlayStrategyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $playerA;

    private User $playerB;

    private Game $chess;

    private Platform $webPlatform;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'features.tournament_v2.enabled' => true,
            'features.playstrategy.enabled' => true,
            'services.playstrategy.base_url' => 'https://playstrategy.org',
            'services.playstrategy.client_id' => 'gamersrival-test',
        ]);

        Http::preventStrayRequests();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LandingPageSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(PlayStrategyGamesSeeder::class);

        $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->admin->assignRole('ADMIN');

        $this->playerA = $this->createPlayer('100.00');
        $this->playerB = $this->createPlayer('100.00');

        $this->chess = Game::query()->where('slug', 'playstrategy-chess')->firstOrFail();
        $this->webPlatform = Platform::query()->where('slug', 'web-browser')->firstOrFail();
    }

    private function createPlayer(string $balance): User
    {
        $user = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $user->assignRole('PLAYER');
        Wallet::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['uuid' => Str::uuid()->toString(), 'cached_balance' => $balance, 'status' => 'active'],
        );

        return $user->fresh('wallet');
    }

    private function linkPlayStrategy(User $user, string $externalId, string $username, string $token): ExternalProviderAccount
    {
        return ExternalProviderAccount::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'provider' => ExternalProviderAccount::PROVIDER_PLAYSTRATEGY,
            'external_user_id' => strtolower($externalId),
            'external_username' => $username,
            'access_token' => $token,
            'scopes' => 'challenge:write',
            'verified_at' => now(),
            'last_synced_at' => now(),
        ]);
    }

    private function createV2PlayStrategyTournament(array $overrides = []): Tournament
    {
        $template = TournamentTemplate::query()->create([
            'uuid' => Str::uuid()->toString(),
            'workflow_version' => 2,
            'game_id' => $this->chess->id,
            'created_by' => $this->admin->id,
            'competition_type' => CompetitionType::TOURNAMENT,
            'name' => 'PlayStrategy Chess Open',
            'format' => 'single_elimination',
            'max_participants' => 2,
            'min_participants' => 2,
            'entry_fee' => '10.00',
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

        return Tournament::query()->create(array_merge([
            'uuid' => Str::uuid()->toString(),
            'workflow_version' => 2,
            'template_id' => $template->id,
            'schedule_slot_id' => $slot->id,
            'occurrence_period_key' => '2026-11-24-'.Str::random(4),
            'game_id' => $this->chess->id,
            'platform_id' => $this->webPlatform->id,
            'platform_ids' => [$this->webPlatform->id],
            'competition_type' => CompetitionType::TOURNAMENT,
            'name' => 'PlayStrategy Chess Open',
            'slug' => 'ps-chess-'.Str::lower(Str::random(6)),
            'status' => TournamentStatus::REGISTRATION_OPEN,
            'entry_fee' => '10.00',
            'prize_pool' => '0.00',
            'max_participants' => 2,
            'min_participants' => 2,
            'team_size' => 1,
            'frequency' => 'daily',
            'start_at' => now()->addHours(1),
            'end_at' => now()->addHours(3),
            'join_closes_at' => now()->addHours(1),
            'created_by' => $this->admin->id,
        ], $overrides));
    }

    public function test_playstrategy_games_display_under_card_and_board_games_and_keep_provider_separate_from_category(): void
    {
        $boardCategory = GameCategory::query()->where('slug', GameCategory::CARD_BOARD_GAMES_SLUG)->firstOrFail();

        $this->assertSame($boardCategory->id, $this->chess->game_category_id);
        $this->assertTrue($this->chess->isPlayStrategyManaged());
        $this->assertSame('standard', $this->chess->playStrategyVariant());

        Livewire::test(LandingPage::class)
            ->call('selectCategory', GameCategory::CARD_BOARD_GAMES_SLUG)
            ->assertSee('Chess')
            ->assertSee('Backgammon')
            ->assertSee('International Draughts')
            ->assertSee('Othello (Flipello)');

        Livewire::test(PublicTournamentList::class)
            ->call('selectCategory', GameCategory::CARD_BOARD_GAMES_SLUG)
            ->assertSee('Chess')
            ->assertSee('Backgammon');
    }

    public function test_oauth_pkce_linking_encrypts_tokens_and_rejects_duplicate_external_account(): void
    {
        Http::fake([
            'https://playstrategy.org/api/token' => Http::response([
                'access_token' => 'ps_live_secret_token_alpha',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'scope' => 'challenge:write',
            ]),
            'https://playstrategy.org/api/account' => Http::response([
                'id' => 'GrandMasterAlpha',
                'username' => 'GrandMasterAlpha',
            ]),
        ]);

        // Start OAuth redirect
        $redirect = $this->actingAs($this->playerA)->get(route('playstrategy.oauth.redirect'));
        $redirect->assertRedirect();
        $location = (string) $redirect->headers->get('Location');
        $this->assertStringStartsWith('https://playstrategy.org/oauth?', $location);
        $this->assertStringContainsString('code_challenge_method=S256', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $state = (string) ($params['state'] ?? '');
        $this->assertNotSame('', $state);

        // Complete OAuth callback
        $this->actingAs($this->playerA)
            ->get(route('playstrategy.oauth.callback', ['code' => 'auth-code-123', 'state' => $state]))
            ->assertRedirect('/profile');

        $account = $this->playerA->playStrategyAccount()->firstOrFail();
        $this->assertSame('grandmasteralpha', $account->external_user_id);
        $this->assertSame('GrandMasterAlpha', $account->external_username);
        $this->assertSame('ps_live_secret_token_alpha', $account->access_token);

        // Raw DB value must be encrypted and hidden from serialization
        $rawToken = DB::table('external_provider_accounts')->where('id', $account->id)->value('access_token');
        $this->assertNotSame('ps_live_secret_token_alpha', $rawToken);
        $this->assertArrayNotHasKey('access_token', $account->toArray());

        // Second player trying to link the same PlayStrategy account must be rejected
        $redirectB = $this->actingAs($this->playerB)->get(route('playstrategy.oauth.redirect'));
        parse_str((string) parse_url((string) $redirectB->headers->get('Location'), PHP_URL_QUERY), $paramsB);

        $this->actingAs($this->playerB)
            ->get(route('playstrategy.oauth.callback', ['code' => 'auth-code-456', 'state' => $paramsB['state']]))
            ->assertRedirect('/profile')
            ->assertSessionHas('error');

        $this->assertNull($this->playerB->playStrategyAccount()->first());
    }

    public function test_registration_requires_linked_account_and_post_commit_creation_syncs_result_atomically(): void
    {
        $tournament = $this->createV2PlayStrategyTournament();

        // Player without linked PlayStrategy account is blocked from registering
        try {
            app(RegisterForV2TournamentAction::class)->execute($tournament, $this->playerA, null, 'PlayerA', 'auto', null, $this->webPlatform->id);
            $this->fail('Expected registration to require linked PlayStrategy account.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('PlayStrategy', $e->getMessage());
        }

        $this->linkPlayStrategy($this->playerA, 'alice_ps', 'Alice_PS', 'token_a');
        $this->linkPlayStrategy($this->playerB, 'bob_ps', 'Bob_PS', 'token_b');

        Http::fake([
            'https://playstrategy.org/api/challenge/*' => Http::response([
                'game' => [
                    'id' => 'psgame001',
                    'url' => 'https://playstrategy.org/psgame001',
                    'status' => 'started',
                ],
            ]),
            'https://playstrategy.org/game/export/psgame001*' => Http::response([
                'id' => 'psgame001',
                'variant' => 'chess',
                'rated' => false,
                'status' => 'mate',
                'winner' => 'p1',
                'players' => [
                    'p1' => ['user' => ['id' => 'alice_ps', 'name' => 'Alice_PS']],
                    'p2' => ['user' => ['id' => 'bob_ps', 'name' => 'Bob_PS']],
                ],
            ]),
        ]);

        // Both players register -> tournament starts -> PlayStrategy game is created once post-commit
        $regA = app(RegisterForV2TournamentAction::class)->execute($tournament, $this->playerA, null, 'Alice_PS', 'auto', null, $this->webPlatform->id);
        $regB = app(RegisterForV2TournamentAction::class)->execute($tournament, $this->playerB, null, 'Bob_PS', 'auto', null, $this->webPlatform->id);

        $match = GameMatch::query()->where('tournament_id', $tournament->id)->firstOrFail();
        $externalGame = $match->activeExternalGame();
        $this->assertNotNull($externalGame);
        $this->assertSame('psgame001', $externalGame->external_game_id);
        $this->assertSame('https://playstrategy.org/psgame001', $externalGame->play_url);
        $this->assertSame(ExternalMatchGame::STATUS_CREATED, $externalGame->status);

        // Duplicate creation call for the same attempt does NOT call PlayStrategy a second time
        app(CreatePlayStrategyMatchGameAction::class)->execute($match->id, 1);
        Http::assertSentCount(1);

        // MatchDetail renders Play Match CTA and hides manual submission form
        Livewire::actingAs($this->playerA)
            ->test(MatchDetail::class, ['uuid' => $match->uuid, 'embedded' => true])
            ->assertSee('Play Match on PlayStrategy')
            ->assertSee('https://playstrategy.org/psgame001');

        // Manual result submission and timeout recovery are blocked on PlayStrategy matches
        foreach ([SubmitMatchResultAction::class, SubmitV2MatchResultAction::class] as $actionClass) {
            try {
                if ($actionClass === SubmitV2MatchResultAction::class) {
                    app(SubmitV2MatchResultAction::class)->execute($match, $this->playerA->id, MatchOutcome::WIN);
                } else {
                    app(SubmitMatchResultAction::class)->execute($match, $this->playerA->id, $regA->id);
                }
                $this->fail('Expected manual submission to be blocked for PlayStrategy match.');
            } catch (LogicException $e) {
                $this->assertStringContainsString('PlayStrategy', $e->getMessage());
            }
        }

        $attempt = MatchAttempt::query()->where('match_id', $match->id)->where('attempt_number', 1)->firstOrFail();
        $this->assertFalse(app(ResolveV2ResultTimeoutAction::class)->execute($attempt->id));

        // Sync verified PlayStrategy result -> completes match, tournament, and pays winner
        app(SyncPlayStrategyMatchResultAction::class)->execute($externalGame->id);

        $match->refresh();
        $tournament->refresh();
        $this->assertSame(MatchStatus::COMPLETED, $match->status);
        $this->assertSame($regA->id, $match->winner_registration_id);
        $this->assertSame(TournamentStatus::COMPLETED, $tournament->status);
        $this->assertSame('paid', $tournament->payout_status);
        $balanceAfterPayout = $this->playerA->wallet->fresh()->cached_balance;
        $this->assertGreaterThan(90.0, (float) $balanceAfterPayout);

        // Repeated/duplicate sync must NOT cause duplicate advancement or payouts
        app(SyncPlayStrategyMatchResultAction::class)->execute($externalGame->id);
        $this->assertSame($balanceAfterPayout, $this->playerA->wallet->fresh()->cached_balance);
    }

    public function test_uncertain_creation_timeout_reconciles_first_and_requires_admin_review_if_unidentified(): void
    {
        Queue::fake([ReconcilePlayStrategyMatchGameJob::class]);

        $this->linkPlayStrategy($this->playerA, 'alice_t', 'Alice_T', 'token_a');
        $this->linkPlayStrategy($this->playerB, 'bob_t', 'Bob_T', 'token_b');

        $tournament = $this->createV2PlayStrategyTournament();

        Http::fake([
            'https://playstrategy.org/api/challenge/*' => fn () => throw new ConnectionException('Timed out waiting for response'),
            'https://playstrategy.org/api/games/user/*' => Http::sequence()
                ->push('', 200)
                ->push(json_encode([
                    'id' => 'psrecovered99',
                    'url' => 'https://playstrategy.org/psrecovered99',
                    'variant' => 'chess',
                    'status' => 'started',
                    'players' => [
                        'p1' => ['user' => ['id' => 'alice_t', 'name' => 'Alice_T']],
                        'p2' => ['user' => ['id' => 'bob_t', 'name' => 'Bob_T']],
                    ],
                ]), 200),
        ]);

        app(RegisterForV2TournamentAction::class)->execute($tournament, $this->playerA, null, 'Alice_T', 'auto', null, $this->webPlatform->id);
        app(RegisterForV2TournamentAction::class)->execute($tournament, $this->playerB, null, 'Bob_T', 'auto', null, $this->webPlatform->id);

        $match = GameMatch::query()->where('tournament_id', $tournament->id)->firstOrFail();
        $external = $match->activeExternalGame();

        $this->assertNotNull($external);
        $this->assertSame(ExternalMatchGame::STATUS_RECONCILING, $external->status);
        Queue::assertPushed(ReconcilePlayStrategyMatchGameJob::class);

        // When reconciliation finds 0 matching games, it requires admin review rather than creating another game
        app(ReconcilePlayStrategyMatchGameAction::class)->execute($external->id);
        $external->refresh();
        $this->assertSame(ExternalMatchGame::STATUS_NEEDS_ADMIN_REVIEW, $external->status);
        $this->assertNull($external->external_game_id);

        // Now test successful reconciliation when 1 exact matching game IS found
        $external->update(['status' => ExternalMatchGame::STATUS_RECONCILING]);

        app(ReconcilePlayStrategyMatchGameAction::class)->execute($external->id);
        $external->refresh();
        $this->assertSame(ExternalMatchGame::STATUS_CREATED, $external->status);
        $this->assertSame('psrecovered99', $external->external_game_id);
    }

    public function test_draws_create_new_attempt_and_new_external_game_while_outages_and_aborts_stay_safe(): void
    {
        $this->linkPlayStrategy($this->playerA, 'alice_d', 'Alice_D', 'token_a');
        $this->linkPlayStrategy($this->playerB, 'bob_d', 'Bob_D', 'token_b');

        $tournament = $this->createV2PlayStrategyTournament();

        $challengeCount = 0;
        Http::fake([
            'https://playstrategy.org/api/challenge/*' => function () use (&$challengeCount) {
                $challengeCount++;

                return Http::response([
                    'game' => [
                        'id' => 'psdraw00'.$challengeCount,
                        'url' => 'https://playstrategy.org/psdraw00'.$challengeCount,
                        'status' => 'started',
                    ],
                ]);
            },
            'https://playstrategy.org/game/export/psdraw001*' => Http::response([
                'id' => 'psdraw001',
                'variant' => 'chess',
                'rated' => false,
                'status' => 'draw',
                'players' => [
                    'p1' => ['user' => ['id' => 'alice_d', 'name' => 'Alice_D']],
                    'p2' => ['user' => ['id' => 'bob_d', 'name' => 'Bob_D']],
                ],
            ]),
            'https://playstrategy.org/game/export/psdraw002*' => Http::sequence()
                ->push(['error' => 'Rate limited'], 429, ['Retry-After' => '45'])
                ->push([
                    'id' => 'psdraw002',
                    'variant' => 'chess',
                    'rated' => false,
                    'status' => 'aborted',
                    'players' => [
                        'p1' => ['user' => ['id' => 'alice_d', 'name' => 'Alice_D']],
                        'p2' => ['user' => ['id' => 'bob_d', 'name' => 'Bob_D']],
                    ],
                ], 200),
        ]);

        $regA = app(RegisterForV2TournamentAction::class)->execute($tournament, $this->playerA, null, 'Alice_D', 'auto', null, $this->webPlatform->id);
        app(RegisterForV2TournamentAction::class)->execute($tournament, $this->playerB, null, 'Bob_D', 'auto', null, $this->webPlatform->id);

        $match = GameMatch::query()->where('tournament_id', $tournament->id)->firstOrFail();
        $firstExternal = $match->activeExternalGame();
        $this->assertSame('psdraw001', $firstExternal?->external_game_id);

        // Draw -> creates attempt #2 and automatically creates external game psdraw002!
        app(SyncPlayStrategyMatchResultAction::class)->execute($firstExternal->id);

        $match->refresh();
        $this->assertSame(2, $match->active_attempt_number);
        $this->assertSame(MatchStatus::IN_PROGRESS, $match->status);
        $this->assertNull($match->winner_registration_id);

        $secondExternal = $match->activeExternalGame();
        $this->assertNotNull($secondExternal);
        $this->assertSame('psdraw002', $secondExternal->external_game_id);

        // First poll hits 429 rate limit -> schedules backoff and does NOT award a winner
        app(SyncPlayStrategyMatchResultAction::class)->execute($secondExternal->id);
        $match->refresh();
        $secondExternal->refresh();
        $this->assertSame(MatchStatus::IN_PROGRESS, $match->status);
        $this->assertNull($match->winner_registration_id);
        $this->assertGreaterThan(0, $secondExternal->poll_attempts);
        $this->assertNotNull($secondExternal->next_poll_at);

        // Second poll sees aborted -> escalates to admin review (DISPUTED), admin override still works!
        app(SyncPlayStrategyMatchResultAction::class)->execute($secondExternal->id);
        $match->refresh();
        $secondExternal->refresh();
        $this->assertSame(MatchStatus::DISPUTED, $match->status);
        $this->assertSame(ExternalMatchGame::STATUS_NEEDS_ADMIN_REVIEW, $secondExternal->status);

        // Authorized admin adjudication remains preserved
        app(OverrideMatchResultAction::class)->execute($match, $regA->id, $this->admin);
        $this->assertSame(MatchStatus::COMPLETED, $match->fresh()->status);
        $this->assertSame($regA->id, $match->fresh()->winner_registration_id);
    }

    public function test_post_commit_job_is_dispatched_after_commit(): void
    {
        Queue::fake([CreatePlayStrategyMatchGameJob::class]);

        $this->linkPlayStrategy($this->playerA, 'alice_q', 'Alice_Q', 'token_a');
        $this->linkPlayStrategy($this->playerB, 'bob_q', 'Bob_Q', 'token_b');

        $tournament = $this->createV2PlayStrategyTournament();
        app(RegisterForV2TournamentAction::class)->execute($tournament, $this->playerA, null, 'Alice_Q', 'auto', null, $this->webPlatform->id);
        app(RegisterForV2TournamentAction::class)->execute($tournament, $this->playerB, null, 'Bob_Q', 'auto', null, $this->webPlatform->id);

        Queue::assertPushed(CreatePlayStrategyMatchGameJob::class, fn (CreatePlayStrategyMatchGameJob $job): bool => $job->afterCommit === true);
    }

    public function test_personal_oauth_token_linking_verifies_account_encrypts_token_and_rejects_duplicates(): void
    {
        Http::fake([
            'https://playstrategy.org/api/account' => Http::response([
                'id' => 'demo_chess_pro',
                'username' => 'Demo_Chess_Pro',
            ]),
        ]);

        $this->actingAs($this->playerA)
            ->post(route('playstrategy.oauth.token'), [
                'playstrategy_token' => 'ps_personal_oauth_token_123456',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHas('success');

        $account = $this->playerA->playStrategyAccount()->firstOrFail();
        $this->assertSame('demo_chess_pro', $account->external_user_id);
        $this->assertSame('Demo_Chess_Pro', $account->external_username);
        $this->assertSame('ps_personal_oauth_token_123456', $account->access_token);

        $rawToken = DB::table('external_provider_accounts')->where('id', $account->id)->value('access_token');
        $this->assertNotSame('ps_personal_oauth_token_123456', $rawToken);

        // Second player cannot link the same PlayStrategy account
        $this->actingAs($this->playerB)
            ->post(route('playstrategy.oauth.token'), [
                'playstrategy_token' => 'ps_personal_oauth_token_123456',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHas('error');

        $this->assertNull($this->playerB->playStrategyAccount()->first());
    }
}
