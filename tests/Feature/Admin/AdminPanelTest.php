<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AuditLogAdmin;
use App\Livewire\Admin\CmsAdmin;
use App\Livewire\Admin\KycAdmin;
use App\Livewire\Admin\MatchAdmin;
use App\Livewire\Admin\TournamentAdmin;
use App\Livewire\Admin\UserAdmin;
use App\Livewire\Admin\WithdrawalAdmin;
use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use App\Shared\Enums\WithdrawalStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $player;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        $this->superAdmin = $this->createUserWithRole('SUPER_ADMIN', 'superadmin@example.com');
        $this->admin = $this->createUserWithRole('ADMIN', 'admin@example.com');
        $this->player = $this->createUserWithRole('PLAYER', 'player@example.com');

        $this->game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'test-game',
            'is_active' => true,
        ]);

        $this->game->translations()->create([
            'locale' => 'en',
            'name' => 'Test Game',
            'description' => 'A test game description',
        ]);
    }

    private function createUserWithRole(string $role, string $email): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => $email,
            'username' => explode('@', $email)[0],
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);

        $user->assignRole($role);

        // Auto create active wallet
        Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'cached_balance' => '100.00',
            'status' => WalletStatus::ACTIVE,
        ]);

        return $user;
    }

    /**
     * Test admin routes authorization.
     */
    public function test_player_cannot_access_admin_dashboard(): void
    {
        $response = $this->actingAs($this->player)->get('/admin');
        $response->assertStatus(403);
        $response->assertSee('Security Protocol Restrict');
    }

    public function test_non_existent_route_shows_custom_404(): void
    {
        $response = $this->get('/non-existent-route-999');
        $response->assertStatus(404);
        $response->assertSee('Grid Signal Lost');
    }

    public function test_admin_visiting_player_profile_redirects_to_admin_profile(): void
    {
        $response = $this->actingAs($this->admin)->get('/profile');
        $response->assertRedirect('/admin/profile');
    }

    public function test_admin_visiting_player_dashboard_redirects_to_admin_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard');
        $response->assertRedirect('/admin');
    }

    public function test_admin_visiting_player_wallet_redirects_to_admin_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/wallet');
        $response->assertRedirect('/admin');
    }

    public function test_admin_visiting_player_teams_redirects_to_admin_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/teams');
        $response->assertRedirect('/admin');
    }

    public function test_player_cannot_access_admin_profile(): void
    {
        $response = $this->actingAs($this->player)->get('/admin/profile');
        $response->assertStatus(403);
        $response->assertSee('Security Protocol Restrict');
    }

    public function test_admin_can_access_admin_profile(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/profile');
        $response->assertStatus(200);
        $response->assertSee('Staff Profile Settings');
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('Dashboard Overview');
        $response->assertSee('Escrow Balance');
    }

    public function test_admin_dashboard_shows_correct_system_status(): void
    {
        // 1. By default, maintenance mode is false, so it should display "System: Online"
        $response = $this->actingAs($this->admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('System: Online');
        $response->assertDontSee('System: Maintenance');

        // 2. Set maintenance mode to true and check
        \App\Modules\Operations\Models\SystemSetting::where('key', 'system.maintenance_mode')->update(['value' => 'true']);

        $response = $this->actingAs($this->admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('System: Maintenance');
        $response->assertDontSee('System: Online');
    }

    public function test_admin_can_access_other_admin_pages(): void
    {
        $pages = [
            '/admin/tournaments' => TournamentAdmin::class,
            '/admin/matches' => MatchAdmin::class,
            '/admin/kyc' => KycAdmin::class,
            '/admin/withdrawals' => WithdrawalAdmin::class,
            '/admin/users' => UserAdmin::class,
            '/admin/audit-logs' => AuditLogAdmin::class,
            '/admin/cms' => CmsAdmin::class,
        ];

        foreach ($pages as $url => $component) {
            $response = $this->actingAs($this->admin)->get($url);
            $response->assertStatus(200);
            $response->assertSee('Admin Panel'); // Sidebar should be visible
            $response->assertStatus(200);
        }
    }

    /**
     * Test TournamentAdmin component functionality.
     */
    public function test_tournament_admin_can_create_tournament(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TournamentAdmin::class)
            ->set('name', 'New Admin Cup')
            ->set('game_id', $this->game->id)
            ->set('entry_fee', '10.00')
            ->set('prize_pool', '150.00')
            ->set('min_participants', 4)
            ->set('max_participants', 16)
            ->set('registration_open_at', now()->addMinutes(5)->format('Y-m-d\TH:i'))
            ->set('registration_close_at', now()->addHours(1)->format('Y-m-d\TH:i'))
            ->set('checkin_open_at', now()->addHours(2)->format('Y-m-d\TH:i'))
            ->set('checkin_close_at', now()->addHours(3)->format('Y-m-d\TH:i'))
            ->set('start_at', now()->addHours(4)->format('Y-m-d\TH:i'))
            ->call('saveTournament');

        $this->assertDatabaseHas('tournaments', [
            'name' => 'New Admin Cup',
            'game_id' => $this->game->id,
            'status' => TournamentStatus::DRAFT->value,
        ]);
    }

    public function test_tournament_admin_can_publish_and_cancel_tournament(): void
    {
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'name' => 'Publish Cup',
            'slug' => 'publish-cup',
            'status' => TournamentStatus::DRAFT,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->admin->id,
            'registration_open_at' => now()->addMinutes(5),
            'registration_close_at' => now()->addHours(1),
            'checkin_open_at' => now()->addHours(2),
            'checkin_close_at' => now()->addHours(3),
            'start_at' => now()->addHours(4),
        ]);

        // Publish
        Livewire::actingAs($this->admin)
            ->test(TournamentAdmin::class)
            ->set('selectedTournamentId', $tournament->id)
            ->call('applyTransition', 'publish');

        $this->assertEquals(TournamentStatus::PUBLISHED, $tournament->fresh()->status);

        // Cancel
        Livewire::actingAs($this->admin)
            ->test(TournamentAdmin::class)
            ->set('selectedTournamentId', $tournament->id)
            ->set('cancelReason', 'Insufficient players registered')
            ->call('cancelTournament');

        $this->assertEquals(TournamentStatus::CANCELLED, $tournament->fresh()->status);
        $this->assertDatabaseHas('tournament_cancellations', [
            'tournament_id' => $tournament->id,
            'reason' => 'Insufficient players registered',
        ]);
    }

    /**
     * Test MatchAdmin component functionality.
     */
    public function test_match_admin_can_override_result(): void
    {
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'name' => 'Match Cup',
            'slug' => 'match-cup',
            'status' => TournamentStatus::ONGOING,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->admin->id,
        ]);

        $bracket = $tournament->brackets()->create(['generated_at' => now()]);
        $round = Round::query()->create(['bracket_id' => $bracket->id, 'round_number' => 1]);

        $regA = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->createUserWithRole('PLAYER', 'playera@example.com')->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        $regB = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->createUserWithRole('PLAYER', 'playerb@example.com')->id,
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

        Livewire::actingAs($this->admin)
            ->test(MatchAdmin::class)
            ->set('selectedMatchId', $match->id)
            ->set('winnerRegistrationId', $regA->id)
            ->call('overrideResult');

        $this->assertEquals(MatchStatus::COMPLETED, $match->fresh()->status);
        $this->assertEquals($regA->id, $match->fresh()->winner_registration_id);
    }

    /**
     * Test KYCAdmin component functionality.
     */
    public function test_kyc_admin_can_approve_kyc(): void
    {
        $kycUser = $this->createUserWithRole('PLAYER', 'kycuser@example.com');
        $kyc = KycSubmission::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $kycUser->id,
            'status' => KycStatus::SUBMITTED,
            'document_type' => 'passport',
            'document_front_path' => 'documents/front.jpg',
        ]);

        Livewire::actingAs($this->admin)
            ->test(KycAdmin::class)
            ->call('selectSubmission', $kyc->id) // Transitions to UNDER_REVIEW
            ->call('approve');

        $this->assertEquals(KycStatus::APPROVED, $kyc->fresh()->status);
        $this->assertEquals($this->admin->id, $kyc->fresh()->reviewed_by);
    }

    /**
     * Test WithdrawalAdmin component functionality.
     */
    public function test_withdrawal_admin_can_approve_withdrawal(): void
    {
        $financeUser = $this->createUserWithRole('PLAYER', 'financeuser@example.com');

        // Add approved KYC to satisfy the guard in ApproveWithdrawalAction
        KycSubmission::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $financeUser->id,
            'status' => KycStatus::APPROVED,
            'document_type' => 'passport',
            'document_front_path' => 'documents/front.jpg',
            'reviewed_by' => $this->admin->id,
            'reviewed_at' => now(),
        ]);

        $withdrawal = Withdrawal::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $financeUser->wallet->id,
            'user_id' => $financeUser->id,
            'amount' => '50.00',
            'status' => WithdrawalStatus::PENDING,
        ]);

        // Review & Approve
        Livewire::actingAs($this->admin)
            ->test(WithdrawalAdmin::class)
            ->call('selectWithdrawal', $withdrawal->id) // Select triggers review check
            ->set('selectedWithdrawalId', $withdrawal->id)
            ->call('approve');

        $this->assertEquals(WithdrawalStatus::APPROVED, $withdrawal->fresh()->status);
        $this->assertEquals($this->admin->id, $withdrawal->fresh()->reviewed_by);
    }
}
