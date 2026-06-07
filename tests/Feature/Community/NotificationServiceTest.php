<?php

declare(strict_types=1);

namespace Tests\Feature\Community;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\Community\Events\BroadcastNotification;
use App\Modules\Community\Models\Notification;
use App\Modules\Community\Models\NotificationPreference;
use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Events\BroadcastMatchCompleted;
use App\Modules\Match\Events\MatchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Events\BroadcastBracketUpdate;
use App\Modules\Tournament\Events\BroadcastTournamentCompleted;
use App\Modules\Tournament\Events\BroadcastTournamentStarted;
use App\Modules\Tournament\Events\TournamentCheckinOpened;
use App\Modules\Tournament\Events\TournamentSeatReserved;
use App\Modules\Tournament\Events\TournamentStarted;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Events\PrizeAwarded;
use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Events\WithdrawalApproved;
use App\Modules\Wallet\Events\WithdrawalRejected;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        $this->notificationService = $this->app->make(NotificationService::class);

        // Create a test user
        $this->user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'player@example.com',
            'username' => 'player_one',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);
    }

    /**
     * Test notification service sends with default preferences.
     */
    public function test_send_respects_default_preferences(): void
    {
        Event::fake([BroadcastNotification::class]);

        $notification = $this->notificationService->send(
            $this->user,
            'test_notification',
            'Hello Title',
            'Hello Message'
        );

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'type' => 'test_notification',
            'title' => 'Hello Title',
            'message' => 'Hello Message',
        ]);

        // Should have created default preferences
        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user->id,
            'email_enabled' => true,
            'in_app_enabled' => true,
            'realtime_enabled' => true,
        ]);

        Event::assertDispatched(BroadcastNotification::class, function ($event) {
            return $event->userUuid === $this->user->uuid
                && $event->notificationData['title'] === 'Hello Title';
        });
    }

    /**
     * Test notification service respects disabled in_app.
     */
    public function test_send_respects_disabled_in_app(): void
    {
        Event::fake([BroadcastNotification::class]);

        NotificationPreference::query()->create([
            'user_id' => $this->user->id,
            'email_enabled' => true,
            'in_app_enabled' => false,
            'realtime_enabled' => true,
        ]);

        $notification = $this->notificationService->send(
            $this->user,
            'test_notification',
            'Hello Title',
            'Hello Message'
        );

        $this->assertNull($notification);
        $this->assertDatabaseCount('notifications', 0);

        Event::assertDispatched(BroadcastNotification::class);
    }

    /**
     * Test notification service respects disabled realtime.
     */
    public function test_send_respects_disabled_realtime(): void
    {
        Event::fake([BroadcastNotification::class]);

        NotificationPreference::query()->create([
            'user_id' => $this->user->id,
            'email_enabled' => true,
            'in_app_enabled' => true,
            'realtime_enabled' => false,
        ]);

        $notification = $this->notificationService->send(
            $this->user,
            'test_notification',
            'Hello Title',
            'Hello Message'
        );

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);

        Event::assertNotDispatched(BroadcastNotification::class);
    }

    /**
     * Test tournament registration confirmed notification.
     */
    public function test_tournament_registration_confirmed_notification(): void
    {
        Event::fake([BroadcastNotification::class]);

        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant',
            'is_active' => true
        ]);
        GameTranslation::query()->create([
            'game_id' => $game->id,
            'locale' => 'en',
            'name' => 'Valorant',
            'description' => '5v5 Shooter',
        ]);

        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'name' => 'Weekly Cup',
            'slug' => 'weekly-cup',
            'status' => TournamentStatus::REGISTRATION_OPEN,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->user->id,
        ]);

        TournamentSeatReserved::dispatch($tournament->id, 123, $this->user->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'registration_confirmed',
            'title' => 'Registration Confirmed',
        ]);
    }

    /**
     * Test check-in reminder notification.
     */
    public function test_checkin_reminder_notification(): void
    {
        Event::fake([BroadcastNotification::class]);

        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant-2',
            'is_active' => true
        ]);
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'name' => 'Weekly Cup',
            'slug' => 'weekly-cup',
            'status' => TournamentStatus::CHECKIN_OPEN,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->user->id,
        ]);

        // Create a registration
        $registration = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        TournamentCheckinOpened::dispatch($tournament->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'checkin_reminder',
            'title' => 'Check-in Reminder',
        ]);
    }

    /**
     * Test tournament started notification & realtime broadcast.
     */
    public function test_tournament_started_notification_and_broadcast(): void
    {
        Event::fake([BroadcastNotification::class, BroadcastTournamentStarted::class]);

        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant-3',
            'is_active' => true
        ]);
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'name' => 'Weekly Cup',
            'slug' => 'weekly-cup',
            'status' => TournamentStatus::ONGOING,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->user->id,
        ]);

        // Create a registration
        $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        TournamentStarted::dispatch($tournament->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'tournament_started',
            'title' => 'Tournament Started',
        ]);

        Event::assertDispatched(BroadcastTournamentStarted::class, function ($event) use ($tournament) {
            return $event->tournamentUuid === $tournament->uuid;
        });
    }

    /**
     * Test prize awarded notification.
     */
    public function test_prize_awarded_notification(): void
    {
        Event::fake([BroadcastNotification::class]);

        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant-4',
            'is_active' => true
        ]);
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'name' => 'Weekly Cup',
            'slug' => 'weekly-cup',
            'status' => TournamentStatus::COMPLETED,
            'entry_fee' => '0.00',
            'prize_pool' => '1000.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->user->id,
        ]);

        $wallet = Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'cached_balance' => '0.00',
            'status' => 'active',
        ]);

        PrizeAwarded::dispatch($wallet->id, $tournament->id, 999, '500.00', 1);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'prize_awarded',
            'title' => 'Prize Awarded',
        ]);
    }

    /**
     * Test deposit confirmed notification.
     */
    public function test_deposit_confirmed_notification(): void
    {
        Event::fake([BroadcastNotification::class]);

        $wallet = Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'cached_balance' => '100.00',
            'status' => 'active',
        ]);

        // Create a real ledger entry so that database FK constraints are satisfied
        $ledgerEntry = LedgerEntry::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $wallet->id,
            'reference_type' => 'deposit',
            'reference_id' => 'test-ref',
            'type' => LedgerType::DEPOSIT,
            'amount' => '100.00',
            'running_balance' => '100.00',
            'description' => 'Test deposit',
            'created_at' => now(),
        ]);

        WalletCredited::dispatch($wallet->id, $ledgerEntry->id, '100.00', 'deposit');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'deposit_completed',
            'title' => 'Deposit Successful',
        ]);
    }

    /**
     * Test withdrawal approved & rejected notifications.
     */
    public function test_withdrawal_notifications(): void
    {
        Event::fake([BroadcastNotification::class]);

        $wallet = Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'cached_balance' => '100.00',
            'status' => 'active',
        ]);

        $withdrawal = Withdrawal::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $wallet->id,
            'user_id' => $this->user->id,
            'amount' => '50.00',
            'status' => 'pending',
        ]);

        WithdrawalApproved::dispatch($withdrawal->id, $wallet->id, 1);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'withdrawal_approved',
            'title' => 'Withdrawal Approved',
        ]);

        WithdrawalRejected::dispatch($withdrawal->id, $wallet->id, 1, 'Invalid KYC');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'withdrawal_rejected',
            'title' => 'Withdrawal Rejected',
        ]);
    }

    /**
     * Test match ready notification.
     */
    public function test_match_ready_notification(): void
    {
        Event::fake([BroadcastNotification::class]);

        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant-5',
            'is_active' => true
        ]);
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'name' => 'Weekly Cup',
            'slug' => 'weekly-cup',
            'status' => TournamentStatus::ONGOING,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->user->id,
        ]);

        $bracket = $tournament->brackets()->create([
            'generated_at' => now(),
        ]);

        $round = Round::query()->create([
            'bracket_id' => $bracket->id,
            'round_number' => 1,
        ]);

        // Create player registrations
        $regA = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        $playerB = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'playerb@example.com',
            'username' => 'player_two',
            'password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        $regB = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $playerB->id,
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

        MatchCreated::dispatch($match->id, $tournament->id, $round->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'match_ready',
            'title' => 'Match Ready',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $playerB->id,
            'type' => 'match_ready',
            'title' => 'Match Ready',
        ]);
    }

    /**
     * Test match broadcast events.
     */
    public function test_match_broadcast_events(): void
    {
        Event::fake([BroadcastMatchCompleted::class, BroadcastBracketUpdate::class]);

        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant-6',
            'is_active' => true
        ]);
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'name' => 'Weekly Cup',
            'slug' => 'weekly-cup',
            'status' => TournamentStatus::ONGOING,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->user->id,
        ]);

        $bracket = $tournament->brackets()->create([
            'generated_at' => now(),
        ]);

        $round = Round::query()->create([
            'bracket_id' => $bracket->id,
            'round_number' => 1,
        ]);

        $registration = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->user->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        $match = GameMatch::query()->create([
            'uuid' => Str::uuid()->toString(),
            'tournament_id' => $tournament->id,
            'round_id' => $round->id,
            'status' => MatchStatus::COMPLETED,
            'winner_registration_id' => $registration->id,
        ]);

        \App\Modules\Match\Events\MatchCompleted::dispatch($match->id, $tournament->id, $registration->id);

        Event::assertDispatched(BroadcastMatchCompleted::class, function ($event) use ($match) {
            return $event->matchUuid === $match->uuid;
        });

        Event::assertDispatched(BroadcastBracketUpdate::class, function ($event) use ($tournament) {
            return $event->tournamentUuid === $tournament->uuid;
        });
    }

    /**
     * Test tournament completed broadcast.
     */
    public function test_tournament_completed_broadcast(): void
    {
        Event::fake([BroadcastTournamentCompleted::class]);

        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant-7',
            'is_active' => true
        ]);
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'name' => 'Weekly Cup',
            'slug' => 'weekly-cup',
            'status' => TournamentStatus::COMPLETED,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->user->id,
        ]);

        \App\Modules\Tournament\Events\TournamentCompleted::dispatch($tournament->id);

        Event::assertDispatched(BroadcastTournamentCompleted::class, function ($event) use ($tournament) {
            return $event->tournamentUuid === $tournament->uuid;
        });
    }
}
