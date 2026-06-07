<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Actions\CancelRegistrationAction;
use App\Modules\Tournament\Actions\CheckinParticipantAction;
use App\Modules\Tournament\Actions\CloseCheckinAction;
use App\Modules\Tournament\Actions\CloseRegistrationAction;
use App\Modules\Tournament\Actions\CompleteTournamentAction;
use App\Modules\Tournament\Actions\CreateTournamentAction;
use App\Modules\Tournament\Actions\CreateTournamentTemplateAction;
use App\Modules\Tournament\Actions\DeleteTournamentTemplateAction;
use App\Modules\Tournament\Actions\GenerateBracketAction;
use App\Modules\Tournament\Actions\OpenCheckinAction;
use App\Modules\Tournament\Actions\OpenRegistrationAction;
use App\Modules\Tournament\Actions\ProcessRefundAction;
use App\Modules\Tournament\Actions\PublishTournamentAction;
use App\Modules\Tournament\Actions\RegisterForTournamentAction;
use App\Modules\Tournament\Actions\StartTournamentAction;
use App\Modules\Tournament\Actions\UpdateTournamentTemplateAction;
use App\Modules\Tournament\Jobs\AutoCancelTournamentJob;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TournamentModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        // Create an admin / tournament organizer
        $this->adminUser = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'organizer@example.com',
            'username' => 'organizer',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('SUPER_ADMIN');

        // Create a Game
        $this->game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant',
            'is_active' => true,
        ]);
        GameTranslation::query()->create([
            'game_id' => $this->game->id,
            'locale' => 'en',
            'name' => 'Valorant',
            'description' => '5v5 tactical shooter',
        ]);
    }

    private function createPlayer(string $email, string $username, float $balance = 100.00): User
    {
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => $email,
            'username' => $username,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $user->assignRole('PLAYER');

        $wallet = Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'cached_balance' => $balance,
            'status' => 'active',
        ]);

        if ($balance > 0) {
            LedgerEntry::query()->create([
                'uuid' => Str::uuid()->toString(),
                'wallet_id' => $wallet->id,
                'reference_type' => User::class,
                'reference_id' => (string) $user->id,
                'type' => LedgerType::DEPOSIT,
                'amount' => $balance,
                'running_balance' => $balance,
                'description' => 'Initial Deposit',
                'created_at' => now(),
            ]);
        }

        return $user;
    }

    public function test_tournament_template_actions(): void
    {
        $createAction = app(CreateTournamentTemplateAction::class);
        $updateAction = app(UpdateTournamentTemplateAction::class);
        $deleteAction = app(DeleteTournamentTemplateAction::class);

        // 1. Create
        $template = $createAction->execute([
            'game_id' => $this->game->id,
            'name' => 'Weekly Cup',
            'format' => 'single_elimination',
            'max_participants' => 8,
            'min_participants' => 4,
            'entry_fee' => 10.00,
            'prizes' => [
                ['position' => 1, 'percentage' => 70.00],
                ['position' => 2, 'percentage' => 30.00],
            ],
        ]);

        $this->assertInstanceOf(TournamentTemplate::class, $template);
        $this->assertDatabaseHas('tournament_templates', ['id' => $template->id, 'name' => 'Weekly Cup']);
        $this->assertCount(2, $template->prizes);

        // 2. Update
        $template = $updateAction->execute($template, [
            'name' => 'Updated Weekly Cup',
            'entry_fee' => 15.00,
            'prizes' => [
                ['position' => 1, 'percentage' => 100.00],
            ],
        ]);

        $this->assertEquals('Updated Weekly Cup', $template->name);
        $this->assertEquals(15.00, $template->entry_fee);
        $this->assertCount(1, $template->prizes);

        // 3. Delete
        $result = $deleteAction->execute($template);
        $this->assertTrue($result);
        $this->assertSoftDeleted('tournament_templates', ['id' => $template->id]);
    }

    public function test_tournament_lifecycle_state_transitions_and_guards(): void
    {
        $createAction = app(CreateTournamentAction::class);
        $publishAction = app(PublishTournamentAction::class);
        $openRegAction = app(OpenRegistrationAction::class);
        $closeRegAction = app(CloseRegistrationAction::class);
        $openCheckinAction = app(OpenCheckinAction::class);
        $closeCheckinAction = app(CloseCheckinAction::class);
        $generateBracketAction = app(GenerateBracketAction::class);
        $startAction = app(StartTournamentAction::class);
        $completeAction = app(CompleteTournamentAction::class);
        $refundAction = app(ProcessRefundAction::class);

        // Create draft
        $tournament = $createAction->execute([
            'name' => 'Summer Brawl',
            'game_id' => $this->game->id,
            'max_participants' => 4,
            'min_participants' => 2,
            'entry_fee' => 10.00,
            'registration_open_at' => now()->addMinutes(5),
            'registration_close_at' => now()->addMinutes(30),
            'checkin_open_at' => now()->addMinutes(35),
            'checkin_close_at' => now()->addMinutes(50),
            'start_at' => now()->addMinutes(60),
        ], $this->adminUser);

        $this->assertEquals(TournamentStatus::DRAFT, $tournament->status);

        // Test guard: Cannot open registration direct from DRAFT
        $this->expectException(InvalidStateTransitionException::class);
        $openRegAction->execute($tournament);
    }

    public function test_cannot_publish_invalid_draft(): void
    {
        $createAction = app(CreateTournamentAction::class);
        $publishAction = app(PublishTournamentAction::class);

        // Missing dates in creation
        $tournament = $createAction->execute([
            'name' => 'Incomplete Tournament',
            'game_id' => $this->game->id,
            'max_participants' => 4,
            'min_participants' => 2,
        ], $this->adminUser);

        $this->expectException(\LogicException::class);
        $publishAction->execute($tournament);
    }

    public function test_registration_flow_with_entry_fees_and_leaves(): void
    {
        $createAction = app(CreateTournamentAction::class);
        $publishAction = app(PublishTournamentAction::class);
        $openRegAction = app(OpenRegistrationAction::class);
        $registerAction = app(RegisterForTournamentAction::class);
        $leaveAction = app(CancelRegistrationAction::class);

        $tournament = $createAction->execute([
            'name' => 'Summer Brawl 2',
            'game_id' => $this->game->id,
            'max_participants' => 4,
            'min_participants' => 2,
            'entry_fee' => 10.00,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        $tournament = $publishAction->execute($tournament);
        $tournament = $openRegAction->execute($tournament);

        $player = $this->createPlayer('player1@example.com', 'player1', 50.00);

        // Register
        $reg = $registerAction->execute($tournament, $player);

        $this->assertEquals(RegistrationStatus::CONFIRMED, $reg->status);
        $this->assertEquals(PaymentStatus::PAID, $reg->payment_status);
        $this->assertEquals(40.00, $player->wallet->fresh()->cached_balance);

        // Leave
        $reg = $leaveAction->execute($reg, $player);

        $this->assertEquals(RegistrationStatus::CANCELLED, $reg->status);
        $this->assertEquals(PaymentStatus::REFUNDED, $reg->payment_status);
        $this->assertEquals(50.00, $player->wallet->fresh()->cached_balance);
    }

    public function test_free_tournament_registration(): void
    {
        $createAction = app(CreateTournamentAction::class);
        $publishAction = app(PublishTournamentAction::class);
        $openRegAction = app(OpenRegistrationAction::class);
        $registerAction = app(RegisterForTournamentAction::class);

        $tournament = $createAction->execute([
            'name' => 'Free Tournament',
            'game_id' => $this->game->id,
            'max_participants' => 4,
            'min_participants' => 2,
            'entry_fee' => 0.00,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        $tournament = $publishAction->execute($tournament);
        $tournament = $openRegAction->execute($tournament);

        $player = $this->createPlayer('player2@example.com', 'player2', 0.00);

        $reg = $registerAction->execute($tournament, $player);

        $this->assertEquals(RegistrationStatus::CONFIRMED, $reg->status);
        $this->assertEquals(PaymentStatus::FREE, $reg->payment_status);
        $this->assertEquals(0.00, $player->wallet->fresh()->cached_balance);
        $this->assertCount(0, $player->wallet->ledgerEntries()->where('type', LedgerType::ENTRY_FEE)->get());
    }

    public function test_bracket_generation_with_byes(): void
    {
        $createAction = app(CreateTournamentAction::class);
        $publishAction = app(PublishTournamentAction::class);
        $openRegAction = app(OpenRegistrationAction::class);
        $registerAction = app(RegisterForTournamentAction::class);
        $closeRegAction = app(CloseRegistrationAction::class);
        $openCheckinAction = app(OpenCheckinAction::class);
        $checkinAction = app(CheckinParticipantAction::class);
        $closeCheckinAction = app(CloseCheckinAction::class);
        $generateBracketAction = app(GenerateBracketAction::class);

        $tournament = $createAction->execute([
            'name' => 'Bye Tournament',
            'game_id' => $this->game->id,
            'max_participants' => 8,
            'min_participants' => 4,
            'entry_fee' => 10.00,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        $tournament = $publishAction->execute($tournament);
        $tournament = $openRegAction->execute($tournament);

        // Register 5 players
        $players = [];
        for ($i = 1; $i <= 5; $i++) {
            $players[$i] = $this->createPlayer("p{$i}@example.com", "p{$i}", 10.00);
            $registerAction->execute($tournament, $players[$i]);
        }

        $tournament = $closeRegAction->execute($tournament);
        $tournament = $openCheckinAction->execute($tournament);

        // Check in all 5 players
        for ($i = 1; $i <= 5; $i++) {
            $checkinAction->execute($tournament, $players[$i]);
        }

        $tournament = $closeCheckinAction->execute($tournament);

        // Generate bracket
        $bracket = $generateBracketAction->execute($tournament);

        $this->assertInstanceOf(Bracket::class, $bracket);
        $this->assertCount(3, $bracket->rounds); // log_2(8) = 3 rounds

        // Verify matches structure in round 1: 4 matches total
        $round1 = $bracket->rounds()->where('round_number', 1)->first();
        $matchesRound1 = GameMatch::where('round_id', $round1->id)->get();
        $this->assertCount(4, $matchesRound1);

        // 5 players, next power of 2 is 8, byes = 8 - 5 = 3.
        // Actual matches: (5 - 3) / 2 = 1 match.
        // Bye matches: 3.
        $completedByes = $matchesRound1->where('status', MatchStatus::COMPLETED);
        $readyMatches = $matchesRound1->where('status', MatchStatus::READY);

        $this->assertCount(3, $completedByes);
        $this->assertCount(1, $readyMatches);

        // Check propagation to round 2 (total match slots = 2)
        $round2 = $bracket->rounds()->where('round_number', 2)->first();
        $matchesRound2 = GameMatch::where('round_id', $round2->id)->get();
        $this->assertCount(2, $matchesRound2);

        // Since byes are completed in round 1, they advance immediately to round 2.
        // Semi matches in round 2 should have players seeded.
        // Let's verify that player_a or player_b is filled from byes.
        $hasSomeParticipantsSeeded = $matchesRound2->contains(fn ($m) => $m->player_a_registration_id !== null || $m->player_b_registration_id !== null);
        $this->assertTrue($hasSomeParticipantsSeeded);
    }

    public function test_auto_cancel_at_checkin_closed(): void
    {
        $createAction = app(CreateTournamentAction::class);
        $publishAction = app(PublishTournamentAction::class);
        $openRegAction = app(OpenRegistrationAction::class);
        $registerAction = app(RegisterForTournamentAction::class);
        $closeRegAction = app(CloseRegistrationAction::class);
        $openCheckinAction = app(OpenCheckinAction::class);
        $closeCheckinAction = app(CloseCheckinAction::class);

        $tournament = $createAction->execute([
            'name' => 'Underpopulated Cup',
            'game_id' => $this->game->id,
            'max_participants' => 8,
            'min_participants' => 4,
            'entry_fee' => 10.00,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        $tournament = $publishAction->execute($tournament);
        $tournament = $openRegAction->execute($tournament);

        // Only register 2 players (min is 4)
        $p1 = $this->createPlayer('p1@example.com', 'p1', 10.00);
        $p2 = $this->createPlayer('p2@example.com', 'p2', 10.00);
        $registerAction->execute($tournament, $p1);
        $registerAction->execute($tournament, $p2);

        $tournament = $closeRegAction->execute($tournament);
        $tournament = $openCheckinAction->execute($tournament);
        $tournament = $closeCheckinAction->execute($tournament);

        // Dispatch AutoCancelTournamentJob
        AutoCancelTournamentJob::dispatchSync($tournament->id);

        $tournament = $tournament->fresh();
        $this->assertEquals(TournamentStatus::CANCELLED, $tournament->status);

        // Verify refunds processed asynchronously
        $this->assertEquals(10.00, $p1->wallet->fresh()->cached_balance);
        $this->assertEquals(10.00, $p2->wallet->fresh()->cached_balance);
    }

    public function test_prize_calculation_and_distribution_on_completion(): void
    {
        $createAction = app(CreateTournamentAction::class);
        $publishAction = app(PublishTournamentAction::class);
        $openRegAction = app(OpenRegistrationAction::class);
        $registerAction = app(RegisterForTournamentAction::class);
        $closeRegAction = app(CloseRegistrationAction::class);
        $openCheckinAction = app(OpenCheckinAction::class);
        $checkinAction = app(CheckinParticipantAction::class);
        $closeCheckinAction = app(CloseCheckinAction::class);
        $generateBracketAction = app(GenerateBracketAction::class);
        $startAction = app(StartTournamentAction::class);
        $completeAction = app(CompleteTournamentAction::class);

        // Create template with prizes percentage
        $template = app(CreateTournamentTemplateAction::class)->execute([
            'game_id' => $this->game->id,
            'name' => 'Priced Template',
            'format' => 'single_elimination',
            'max_participants' => 4,
            'min_participants' => 2,
            'entry_fee' => 20.00,
            'prizes' => [
                ['position' => 1, 'percentage' => 70.00],
                ['position' => 2, 'percentage' => 30.00],
            ],
        ]);

        // Create tournament from template
        $tournament = $createAction->execute([
            'name' => 'Priced Cup',
            'game_id' => $this->game->id,
            'template_id' => $template->id,
            'max_participants' => 4,
            'min_participants' => 2,
            'entry_fee' => 20.00,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        $tournament = $publishAction->execute($tournament);
        $tournament = $openRegAction->execute($tournament);

        $p1 = $this->createPlayer('pl1@example.com', 'pl1', 20.00);
        $p2 = $this->createPlayer('pl2@example.com', 'pl2', 20.00);

        $registerAction->execute($tournament, $p1);
        $registerAction->execute($tournament, $p2);

        $tournament = $closeRegAction->execute($tournament);

        // Total entry fees: 2 * 20.00 = 40.00.
        // platform.rake_percentage is 10%.
        // Rake = 40 * 0.10 = 4.00.
        // Prize pool = 40.00 - 4.00 = 36.00.
        // Stored prize pool check
        $this->assertEquals(36.00, (float) $tournament->prize_pool);

        $tournament = $openCheckinAction->execute($tournament);
        $checkinAction->execute($tournament, $p1);
        $checkinAction->execute($tournament, $p2);
        $tournament = $closeCheckinAction->execute($tournament);

        $bracket = $generateBracketAction->execute($tournament);
        $tournament = $startAction->execute($tournament);

        // Complete the final match: let's pretend $p1 won.
        $finalRound = $bracket->rounds()->where('round_number', 1)->first();
        $finalMatch = GameMatch::where('round_id', $finalRound->id)->first();
        $finalMatch->winner_registration_id = $tournament->registrations()->where('user_id', $p1->id)->first()->id;
        $finalMatch->status = MatchStatus::COMPLETED;
        $finalMatch->save();

        $tournament = $completeAction->execute($tournament);

        // Prizes distributed:
        // Rank 1 (Player 1) gets 70% of 36.00 = 25.20.
        // Rank 2 (Player 2) gets 30% of 36.00 = 10.80.
        // Rounded remainder: 36.00 - 25.20 - 10.80 = 0.00.
        $this->assertEquals(25.20, $p1->wallet->fresh()->cached_balance);
        $this->assertEquals(10.80, $p2->wallet->fresh()->cached_balance);

        // Platform wallet check:
        // Platform starting balance was 0.00.
        // Rake (4.00) + Remainder (0.00) = 4.00.
        $platformUser = User::query()->where('email', 'platform@playersaloons.com')->first();
        $this->assertEquals(4.00, $platformUser->wallet->fresh()->cached_balance);
    }
}
