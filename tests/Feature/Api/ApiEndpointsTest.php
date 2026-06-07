<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\CMS\Models\Game;
use App\Modules\Community\Models\Notification;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserProfile;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Team\Models\Team;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\CheckinStatus;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $player;
    private User $organizer;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        // Create game
        $this->game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant',
            'is_active' => true,
        ]);

        // Create player
        $this->player = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'player@example.com',
            'username' => 'playerone',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);
        $this->player->assignRole('PLAYER');

        // Create player profile
        UserProfile::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->player->id,
            'display_name' => 'Player One',
            'country_code' => 'US',
            'timezone' => 'America/New_York',
            'bio' => 'An avid player',
        ]);

        // Create player wallet
        Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->player->id,
            'cached_balance' => '100.00',
            'status' => WalletStatus::ACTIVE,
        ]);

        // Create organizer
        $this->organizer = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'organizer@example.com',
            'username' => 'orgmaster',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);
        $this->organizer->assignRole('TOURNAMENT_ORGANIZER');
    }

    /**
     * Test Tournament list & show endpoints (Public).
     */
    public function test_tournament_public_endpoints(): void
    {
        // Create tournaments
        $t1 = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'name' => 'Weekly Open 1',
            'slug' => 'weekly-open-1',
            'status' => TournamentStatus::REGISTRATION_OPEN,
            'entry_fee' => '10.00',
            'prize_pool' => '100.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->organizer->id,
        ]);

        $t2 = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'name' => 'Weekly Open 2',
            'slug' => 'weekly-open-2',
            'status' => TournamentStatus::DRAFT,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->organizer->id,
        ]);

        // 1. List tournaments (Public)
        $response = $this->getJson(route('api.v1.tournaments.index'));
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid', 'name', 'slug', 'status', 'entry_fee', 'prize_pool',
                        'max_participants', 'min_participants', 'game',
                    ],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);

        // Filter by status
        $responseFiltered = $this->getJson(route('api.v1.tournaments.index', ['status' => 'REGISTRATION_OPEN']));
        $responseFiltered->assertStatus(200);
        $this->assertCount(1, $responseFiltered->json('data'));
        $this->assertEquals($t1->uuid, $responseFiltered->json('data.0.uuid'));

        // 2. Show tournament (Public)
        $responseShow = $this->getJson(route('api.v1.tournaments.show', ['uuid' => $t1->uuid]));
        $responseShow->assertStatus(200)
            ->assertJsonPath('data.uuid', $t1->uuid)
            ->assertJsonPath('data.name', 'Weekly Open 1')
            ->assertJsonMissing(['id']); // No internal database IDs leaked!
    }

    /**
     * Test Tournament registration and check-in (Auth).
     */
    public function test_tournament_auth_endpoints(): void
    {
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'name' => 'Pro Cup',
            'slug' => 'pro-cup',
            'status' => TournamentStatus::REGISTRATION_OPEN,
            'entry_fee' => '20.00',
            'prize_pool' => '200.00',
            'max_participants' => 16,
            'min_participants' => 4,
            'created_by' => $this->organizer->id,
        ]);

        // Unauthenticated register
        $this->postJson(route('api.v1.tournaments.register', ['uuid' => $tournament->uuid]))
            ->assertStatus(401);

        // Authenticated register
        Sanctum::actingAs($this->player);
        $responseReg = $this->postJson(route('api.v1.tournaments.register', ['uuid' => $tournament->uuid]));
        $responseReg->assertStatus(201)
            ->assertJsonPath('message', 'Successfully registered for the tournament.')
            ->assertJsonStructure(['registration' => ['uuid', 'status']]);

        // Assert fee deducted (100 - 20 = 80)
        $this->assertEquals(80.00, (float) $this->player->wallet->fresh()->cached_balance);

        // Checkin test
        // 1. Checkin when tournament status is not checkin_open (should return 422)
        $this->postJson(route('api.v1.tournaments.checkin', ['uuid' => $tournament->uuid]))
            ->assertStatus(422);

        // 2. Open check-in
        $tournament->update(['status' => TournamentStatus::CHECKIN_OPEN]);

        // 3. Perform check-in
        $responseCheckin = $this->postJson(route('api.v1.tournaments.checkin', ['uuid' => $tournament->uuid]));
        $responseCheckin->assertStatus(200)
            ->assertJsonPath('message', 'Successfully checked in for the tournament.')
            ->assertJsonStructure(['checkin' => ['status', 'checked_in_at']]);
    }

    /**
     * Test Match endpoints.
     */
    public function test_match_endpoints(): void
    {
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'name' => 'Pro Cup',
            'slug' => 'pro-cup',
            'status' => TournamentStatus::ONGOING,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->organizer->id,
        ]);

        $bracket = $tournament->brackets()->create(['generated_at' => now()]);
        $round = Round::query()->create(['bracket_id' => $bracket->id, 'round_number' => 1]);

        $opponent = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'opp@example.com',
            'username' => 'opp',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);
        $opponent->assignRole('PLAYER');

        $regA = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->player->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        $regB = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $opponent->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        $match = GameMatch::query()->create([
            'uuid' => Str::uuid()->toString(),
            'tournament_id' => $tournament->id,
            'round_id' => $round->id,
            'player_a_registration_id' => $regA->id,
            'player_b_registration_id' => $regB->id,
            'status' => MatchStatus::READY,
        ]);

        // Unauthenticated match view
        $this->getJson(route('api.v1.matches.show', ['uuid' => $match->uuid]))
            ->assertStatus(401);

        // Authenticated view
        Sanctum::actingAs($this->player);
        $responseShow = $this->getJson(route('api.v1.matches.show', ['uuid' => $match->uuid]));
        $responseShow->assertStatus(200)
            ->assertJsonPath('data.uuid', $match->uuid)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.player_a.username', 'playerone')
            ->assertJsonPath('data.player_b.username', 'opp')
            ->assertJsonMissing(['id']);

        // Submit Result tests
        // 1. Status is ready (needs to be in_progress to submit result)
        $this->postJson(route('api.v1.matches.result', ['uuid' => $match->uuid]), [
            'winner_user_uuid' => $this->player->uuid,
            'notes' => 'I won!',
        ])->assertStatus(422);

        $match->update(['status' => MatchStatus::IN_PROGRESS]);

        // 2. Submit result as a third-party player (should return 403)
        $unrelatedUser = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'unrelated@example.com',
            'username' => 'unrelated',
            'password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        $unrelatedUser->assignRole('PLAYER');

        Sanctum::actingAs($unrelatedUser);
        $this->postJson(route('api.v1.matches.result', ['uuid' => $match->uuid]), [
            'winner_user_uuid' => $this->player->uuid,
        ])->assertStatus(403);

        // 3. Submit valid result as participant
        Sanctum::actingAs($this->player);
        $responseResult = $this->postJson(route('api.v1.matches.result', ['uuid' => $match->uuid]), [
            'winner_user_uuid' => $this->player->uuid,
            'notes' => 'I won!',
        ]);
        $responseResult->assertStatus(200)
            ->assertJsonPath('message', 'Result submitted successfully.')
            ->assertJsonStructure(['submission' => ['uuid', 'winner_user_uuid']]);

        // Dispute test
        // 1. Submit dispute as participant
        $responseDispute = $this->postJson(route('api.v1.matches.dispute', ['uuid' => $match->uuid]));
        $responseDispute->assertStatus(201)
            ->assertJsonPath('message', 'Dispute opened successfully.')
            ->assertJsonStructure(['dispute' => ['uuid', 'status']]);
    }

    /**
     * Test Wallet endpoints.
     */
    public function test_wallet_endpoints(): void
    {
        Sanctum::actingAs($this->player);

        // 1. Get Balance
        $responseBalance = $this->getJson(route('api.v1.wallet.balance'));
        $responseBalance->assertStatus(200)
            ->assertJsonPath('data.balance', '100.00')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissing(['id']);

        // 2. List Transactions (Empty initially)
        $responseTx = $this->getJson(route('api.v1.wallet.transactions'));
        $responseTx->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);

        // 3. Request Withdrawal
        // KYC needs to be approved first
        $this->postJson(route('api.v1.wallet.withdraw'), [
            'amount' => 50.00,
        ])->assertStatus(422); // KYC not approved yet

        // Approve KYC
        KycSubmission::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->player->id,
            'status' => KycStatus::APPROVED,
            'document_type' => 'passport',
            'paths' => json_encode(['path']),
        ]);

        // Request valid withdrawal
        $responseWithdrawal = $this->postJson(route('api.v1.wallet.withdraw'), [
            'amount' => 50.00,
        ]);
        $responseWithdrawal->assertStatus(201)
            ->assertJsonPath('data.amount', '50.00')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissing(['id']);
    }

    /**
     * Test Profile endpoints.
     */
    public function test_profile_endpoints(): void
    {
        Sanctum::actingAs($this->player);

        // 1. Show Profile
        $responseShow = $this->getJson(route('api.v1.profile.show'));
        $responseShow->assertStatus(200)
            ->assertJsonPath('data.username', 'playerone')
            ->assertJsonPath('data.profile.display_name', 'Player One')
            // Crucial security/requirement check: Referral URL displays database ID
            ->assertJsonPath('data.referral_url', url('/register?ref=' . $this->player->id))
            ->assertJsonMissing(['id']);

        // 2. Update Profile
        $responseUpdate = $this->putJson(route('api.v1.profile.update'), [
            'display_name' => 'Player One Modified',
            'bio' => 'My new bio',
            'country_code' => 'CA',
            'timezone' => 'America/Toronto',
        ]);
        $responseUpdate->assertStatus(200)
            ->assertJsonPath('data.profile.display_name', 'Player One Modified')
            ->assertJsonPath('data.profile.bio', 'My new bio')
            ->assertJsonPath('data.profile.country_code', 'CA')
            ->assertJsonPath('data.profile.timezone', 'America/Toronto');
    }

    /**
     * Test Team endpoints.
     */
    public function test_team_endpoints(): void
    {
        Sanctum::actingAs($this->player);

        // 1. Create Team
        $responseCreate = $this->postJson(route('api.v1.teams.create'), [
            'name' => 'Team Dynamic',
            'logo_path' => 'teams/logo.png',
        ]);
        $responseCreate->assertStatus(201)
            ->assertJsonPath('data.name', 'Team Dynamic')
            ->assertJsonPath('data.captain.username', 'playerone')
            ->assertJsonMissing(['id']);

        $teamUuid = $responseCreate->json('data.uuid');

        // 2. Show Team
        $responseShow = $this->getJson(route('api.v1.teams.show', ['uuid' => $teamUuid]));
        $responseShow->assertStatus(200)
            ->assertJsonPath('data.uuid', $teamUuid)
            ->assertJsonPath('data.name', 'Team Dynamic');

        // 3. Invite Member
        $invitedUser = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'invitee@example.com',
            'username' => 'invitee',
            'password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $responseInvite = $this->postJson(route('api.v1.teams.invite', ['uuid' => $teamUuid]), [
            'invited_user_uuid' => $invitedUser->uuid,
        ]);
        $responseInvite->assertStatus(201)
            ->assertJsonPath('message', 'Invitation sent successfully.')
            ->assertJsonStructure(['invitation' => ['uuid', 'status']]);
    }

    /**
     * Test Notification endpoints.
     */
    public function test_notification_endpoints(): void
    {
        // Seed a notification for the player
        $notification = Notification::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->player->id,
            'type' => 'info',
            'title' => 'Welcome Alert',
            'message' => 'Thanks for signing up.',
            'read_at' => null,
        ]);

        Sanctum::actingAs($this->player);

        // 1. List notifications
        $responseList = $this->getJson(route('api.v1.notifications.index'));
        $responseList->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('data.0.uuid', $notification->uuid)
            ->assertJsonPath('data.0.read_at', null);

        // 2. Mark notification read
        $responseRead = $this->postJson(route('api.v1.notifications.read', ['uuid' => $notification->uuid]));
        $responseRead->assertStatus(200)
            ->assertJsonPath('message', 'Notification marked as read.')
            ->assertJsonStructure(['notification' => ['uuid', 'read_at']])
            ->assertJsonMissing(['id']);

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
