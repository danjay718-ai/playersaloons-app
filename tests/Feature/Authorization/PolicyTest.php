<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Team\Models\Team;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $admin;
    private User $organizer;
    private User $financeOperator;
    private User $kycReviewer;
    private User $playerA;
    private User $playerB;
    private User $playerC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        // Create Users with distinct roles
        $this->superAdmin = $this->createUserWithRole('SUPER_ADMIN', 'superadmin@example.com');
        $this->admin = $this->createUserWithRole('ADMIN', 'admin@example.com');
        $this->organizer = $this->createUserWithRole('TOURNAMENT_ORGANIZER', 'organizer@example.com');
        $this->financeOperator = $this->createUserWithRole('FINANCE_OPERATOR', 'finance@example.com');
        $this->kycReviewer = $this->createUserWithRole('KYC_REVIEWER', 'kyc@example.com');

        $this->playerA = $this->createUserWithRole('PLAYER', 'playera@example.com');
        $this->playerB = $this->createUserWithRole('PLAYER', 'playerb@example.com');
        $this->playerC = $this->createUserWithRole('PLAYER', 'playerc@example.com');
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

        return $user;
    }

    /**
     * Test TournamentPolicy.
     */
    public function test_tournament_policy(): void
    {
        $game = Game::query()->create(['uuid' => Str::uuid()->toString(), 'slug' => 'val-t', 'is_active' => true]);

        // Tournament owned by organizer
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $game->id,
            'name' => 'Weekly Cup',
            'slug' => 'weekly-cup',
            'status' => TournamentStatus::DRAFT,
            'entry_fee' => '0.00',
            'prize_pool' => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by' => $this->organizer->id,
        ]);

        // 1. Create check
        $this->assertTrue($this->organizer->can('create', Tournament::class));
        $this->assertFalse($this->playerA->can('create', Tournament::class));

        // 2. Publish checks
        $this->assertTrue($this->organizer->can('publish', $tournament));
        $this->assertTrue($this->admin->can('publish', $tournament));
        $this->assertTrue($this->superAdmin->can('publish', $tournament));

        // Other organizer should NOT be able to publish
        $otherOrganizer = $this->createUserWithRole('TOURNAMENT_ORGANIZER', 'otherorg@example.com');
        $this->assertFalse($otherOrganizer->can('publish', $tournament));
        $this->assertFalse($this->playerA->can('publish', $tournament));

        // 3. Manage checks
        $this->assertTrue($this->organizer->can('manage', $tournament));
        $this->assertFalse($otherOrganizer->can('manage', $tournament));

        // 4. Cancel checks
        $this->assertTrue($this->organizer->can('cancel', $tournament));
        $this->assertFalse($otherOrganizer->can('cancel', $tournament));
    }

    /**
     * Test MatchPolicy.
     */
    public function test_match_policy(): void
    {
        $game = Game::query()->create(['uuid' => Str::uuid()->toString(), 'slug' => 'val-m', 'is_active' => true]);
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
            'created_by' => $this->organizer->id,
        ]);

        $bracket = $tournament->brackets()->create(['generated_at' => now()]);
        $round = Round::query()->create(['bracket_id' => $bracket->id, 'round_number' => 1]);

        $regA = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->playerA->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        $regB = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->playerB->id,
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

        // Start checks
        $this->assertTrue($this->organizer->can('start', $match));
        $this->assertFalse($this->playerA->can('start', $match));

        // SubmitResult checks
        $this->assertTrue($this->playerA->can('submitResult', $match));
        $this->assertTrue($this->playerB->can('submitResult', $match));
        $this->assertFalse($this->playerC->can('submitResult', $match)); // Player C is not in the match

        // Dispute checks
        $this->assertTrue($this->playerA->can('dispute', $match));
        $this->assertTrue($this->playerB->can('dispute', $match));
        $this->assertFalse($this->playerC->can('dispute', $match));
    }

    /**
     * Test WalletPolicy.
     */
    public function test_wallet_policy(): void
    {
        $walletA = Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->playerA->id,
            'cached_balance' => '0.00',
            'status' => WalletStatus::ACTIVE,
        ]);

        // View checks
        $this->assertTrue($this->playerA->can('view', $walletA));
        $this->assertFalse($this->playerB->can('view', $walletA));
        $this->assertTrue($this->admin->can('view', $walletA));
        $this->assertTrue($this->financeOperator->can('view', $walletA));

        // RequestWithdrawal checks
        $this->assertTrue($this->playerA->can('requestWithdrawal', $walletA));
        $this->assertFalse($this->playerB->can('requestWithdrawal', $walletA));

        // Freeze / Unfreeze checks
        $this->assertTrue($this->admin->can('freeze', $walletA));
        $this->assertTrue($this->superAdmin->can('freeze', $walletA));

        $this->assertFalse($this->admin->can('unfreeze', $walletA)); // ONLY SUPER_ADMIN can unfreeze
        $this->assertTrue($this->superAdmin->can('unfreeze', $walletA));
    }

    /**
     * Test WithdrawalPolicy (Four-Eyes Enforcement).
     */
    public function test_withdrawal_policy_four_eyes(): void
    {
        $walletA = Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->playerA->id,
            'cached_balance' => '100.00',
            'status' => WalletStatus::ACTIVE,
        ]);

        $withdrawal = Withdrawal::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $walletA->id,
            'user_id' => $this->playerA->id,
            'amount' => '50.00',
            'status' => 'pending',
        ]);

        // Owner cannot review/approve/reject own withdrawal
        $this->assertFalse($this->playerA->can('review', $withdrawal));
        $this->assertFalse($this->playerA->can('approve', $withdrawal));
        $this->assertFalse($this->playerA->can('reject', $withdrawal));

        // Finance operator / Admin (who are not the owner) can approve
        $this->assertTrue($this->financeOperator->can('approve', $withdrawal));
        $this->assertTrue($this->admin->can('approve', $withdrawal));
        $this->assertTrue($this->superAdmin->can('approve', $withdrawal));

        // If a Finance operator makes their own withdrawal request, they cannot self-approve
        $walletFO = Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->financeOperator->id,
            'cached_balance' => '100.00',
            'status' => WalletStatus::ACTIVE,
        ]);
        $withdrawalFO = Withdrawal::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $walletFO->id,
            'user_id' => $this->financeOperator->id,
            'amount' => '50.00',
            'status' => 'pending',
        ]);

        $this->assertFalse($this->financeOperator->can('approve', $withdrawalFO)); // Denied due to self-approval
        $this->assertTrue($this->admin->can('approve', $withdrawalFO)); // Admin can approve it
    }

    /**
     * Test KycPolicy.
     */
    public function test_kyc_policy(): void
    {
        $kyc = KycSubmission::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->playerA->id,
            'status' => 'submitted',
            'document_type' => 'id_card',
            'paths' => json_encode(['path1']),
        ]);

        // View checks
        $this->assertTrue($this->playerA->can('view', $kyc));
        $this->assertFalse($this->playerB->can('view', $kyc));
        $this->assertTrue($this->kycReviewer->can('view', $kyc));
        $this->assertTrue($this->admin->can('view', $kyc));

        // Review Workflow checks
        $this->assertTrue($this->kycReviewer->can('review', $kyc));
        $this->assertTrue($this->kycReviewer->can('approve', $kyc));
        $this->assertTrue($this->kycReviewer->can('reject', $kyc));

        $this->assertFalse($this->playerA->can('approve', $kyc));
    }

    /**
     * Test TeamPolicy.
     */
    public function test_team_policy(): void
    {
        $team = Team::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Alpha Team',
            'slug' => 'alpha-team',
            'captain_user_id' => $this->playerA->id,
            'status' => 'active',
        ]);

        // Create check
        $this->assertTrue($this->playerA->can('create', Team::class));

        // Manage / Invite / Remove checks
        $this->assertTrue($this->playerA->can('manage', $team));
        $this->assertTrue($this->playerA->can('invite', $team));
        $this->assertTrue($this->playerA->can('remove', $team));

        // Non-captain member cannot manage
        $this->assertFalse($this->playerB->can('manage', $team));
        $this->assertFalse($this->playerB->can('invite', $team));
        $this->assertFalse($this->playerB->can('remove', $team));

        // Admin can manage
        $this->assertTrue($this->admin->can('manage', $team));
    }

    /**
     * Test UserPolicy.
     */
    public function test_user_policy(): void
    {
        $this->assertTrue($this->admin->can('suspend', $this->playerA));
        $this->assertTrue($this->admin->can('unsuspend', $this->playerA));
        $this->assertTrue($this->admin->can('assignRole', $this->playerA));
        $this->assertTrue($this->admin->can('revokeRole', $this->playerA));

        $this->assertFalse($this->playerA->can('suspend', $this->playerB));
    }

    /**
     * Test DisputePolicy.
     */
    public function test_dispute_policy(): void
    {
        $game = Game::query()->create(['uuid' => Str::uuid()->toString(), 'slug' => 'val-d', 'is_active' => true]);
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
            'created_by' => $this->organizer->id,
        ]);

        $bracket = $tournament->brackets()->create(['generated_at' => now()]);
        $round = Round::query()->create(['bracket_id' => $bracket->id, 'round_number' => 1]);

        $regA = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->playerA->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        $regB = $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->playerB->id,
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

        $dispute = MatchDispute::query()->create([
            'uuid' => Str::uuid()->toString(),
            'match_id' => $match->id,
            'opened_by' => $this->playerA->id,
            'status' => DisputeStatus::OPEN,
        ]);

        // View checks
        $this->assertTrue($this->playerA->can('view', $dispute));
        $this->assertTrue($this->playerB->can('view', $dispute));
        $this->assertFalse($this->playerC->can('view', $dispute));
        $this->assertTrue($this->organizer->can('view', $dispute));

        // Resolve checks
        $this->assertTrue($this->organizer->can('resolve', $dispute));
        $this->assertFalse($this->playerA->can('resolve', $dispute));
    }
}
