<?php

declare(strict_types=1);

namespace Tests\Feature\Match;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\ConfirmMatchResultAction;
use App\Modules\Match\Actions\SubmitMatchResultAction;
use App\Modules\Match\Jobs\AutoForfeitJob;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Actions\CheckinParticipantAction;
use App\Modules\Tournament\Actions\CloseCheckinAction;
use App\Modules\Tournament\Actions\CloseRegistrationAction;
use App\Modules\Tournament\Actions\CreateTournamentAction;
use App\Modules\Tournament\Actions\GenerateBracketAction;
use App\Modules\Tournament\Actions\OpenCheckinAction;
use App\Modules\Tournament\Actions\OpenRegistrationAction;
use App\Modules\Tournament\Actions\PublishTournamentAction;
use App\Modules\Tournament\Actions\RegisterForTournamentAction;
use App\Modules\Tournament\Actions\StartTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\PaymentStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConfirmResultFlowTest extends TestCase
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

        $this->adminUser = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('SUPER_ADMIN');

        $this->game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'csgo',
            'is_active' => true,
        ]);
        GameTranslation::query()->create([
            'game_id' => $this->game->id,
            'locale' => 'en',
            'name' => 'CS:GO',
            'description' => 'CS:GO FPS game',
        ]);
    }

    private function createPlayer(string $email, string $username): User
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

        \App\Modules\Wallet\Models\Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'cached_balance' => 100.00,
            'status' => 'active',
        ]);

        return $user;
    }

    /**
     * Test the full confirmResult -> MatchCompleted -> AdvanceWinnerListener flow.
     */
    public function test_full_confirm_result_and_progression_flow(): void
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
        $startTournamentAction = app(StartTournamentAction::class);

        // Setup a 4-player tournament
        $tournament = $createAction->execute([
            'name' => 'Championship 4 Players',
            'game_id' => $this->game->id,
            'max_participants' => 4,
            'min_participants' => 4,
            'entry_fee' => 0.00,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        $tournament = $publishAction->execute($tournament);
        $tournament = $openRegAction->execute($tournament);

        $playerA = $this->createPlayer('playerA@example.com', 'playerA');
        $playerB = $this->createPlayer('playerB@example.com', 'playerB');
        $playerC = $this->createPlayer('playerC@example.com', 'playerC');
        $playerD = $this->createPlayer('playerD@example.com', 'playerD');

        $regA = $registerAction->execute($tournament, $playerA);
        $regB = $registerAction->execute($tournament, $playerB);
        $regC = $registerAction->execute($tournament, $playerC);
        $regD = $registerAction->execute($tournament, $playerD);

        $tournament = $closeRegAction->execute($tournament);
        $tournament = $openCheckinAction->execute($tournament);

        $checkinAction->execute($tournament, $playerA);
        $checkinAction->execute($tournament, $playerB);
        $checkinAction->execute($tournament, $playerC);
        $checkinAction->execute($tournament, $playerD);

        $tournament = $closeCheckinAction->execute($tournament);

        // Generate bracket and start tournament
        $bracket = $generateBracketAction->execute($tournament);
        $tournament = $startTournamentAction->execute($tournament);

        $this->assertEquals(TournamentStatus::ONGOING, $tournament->status);

        // Round 1 should have 2 matches
        $round1 = $bracket->rounds()->where('round_number', 1)->first();
        $round1Matches = GameMatch::where('round_id', $round1->id)->orderBy('id')->get();
        $this->assertCount(2, $round1Matches);

        $match1 = $round1Matches[0];
        $match2 = $round1Matches[1];

        // AutoStartMatchesListener starts ready round-one matches when the tournament starts.
        $this->assertEquals(MatchStatus::IN_PROGRESS, $match1->fresh()->status);
        $this->assertEquals(MatchStatus::IN_PROGRESS, $match2->fresh()->status);

        // Complete Match 1: Player A claims win, Player B confirms
        $match1RegA = $match1->playerARegistration;
        $match1RegB = $match1->playerBRegistration;
        $playerAUserId = $match1RegA->user_id;
        $playerBUserId = $match1RegB->user_id;

        $submitAction = app(SubmitMatchResultAction::class);
        $confirmAction = app(ConfirmMatchResultAction::class);

        $submitAction->execute($match1, $playerAUserId, $match1RegA->id, 'I won');
        $this->assertEquals(MatchStatus::WAITING_FOR_CONFIRMATION, $match1->fresh()->status);

        $confirmAction->execute($match1, $playerBUserId);
        $this->assertEquals(MatchStatus::COMPLETED, $match1->fresh()->status);
        $this->assertEquals($match1RegA->id, $match1->fresh()->winner_registration_id);

        // Verify propagation of Match 1 winner to Round 2 Match 1
        $round2 = $bracket->rounds()->where('round_number', 2)->first();
        $round2Matches = GameMatch::where('round_id', $round2->id)->orderBy('id')->get();
        $this->assertCount(1, $round2Matches);
        $r2Match = $round2Matches[0];

        $this->assertEquals($match1RegA->id, $r2Match->fresh()->player_a_registration_id);
        $this->assertNull($r2Match->fresh()->player_b_registration_id);
        $this->assertEquals(MatchStatus::PENDING, $r2Match->fresh()->status);

        // Complete Match 2: Player C claims win, Player D confirms
        $match2RegA = $match2->playerARegistration;
        $match2RegB = $match2->playerBRegistration;
        $match2PlayerAUserId = $match2RegA->user_id;
        $match2PlayerBUserId = $match2RegB->user_id;

        $submitAction->execute($match2, $match2PlayerAUserId, $match2RegA->id, 'I won');
        $confirmAction->execute($match2, $match2PlayerBUserId);

        $this->assertEquals(MatchStatus::COMPLETED, $match2->fresh()->status);
        $this->assertEquals($match2RegA->id, $match2->fresh()->winner_registration_id);

        // Verify that Round 2 Match 1 now has both player A and player B slots filled, and is IN_PROGRESS
        $r2Match = $r2Match->fresh();
        $this->assertEquals($match1RegA->id, $r2Match->player_a_registration_id);
        $this->assertEquals($match2RegA->id, $r2Match->player_b_registration_id);
        $this->assertEquals(MatchStatus::IN_PROGRESS, $r2Match->status);
    }

    /**
     * Test AutoForfeitJob triggers forfeit when result confirmation is timed out.
     */
    public function test_auto_forfeit_job_timeout(): void
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
        $startTournamentAction = app(StartTournamentAction::class);

        $tournament = $createAction->execute([
            'name' => 'Timeout Cup',
            'game_id' => $this->game->id,
            'max_participants' => 2,
            'min_participants' => 2,
            'entry_fee' => 0.00,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        // Explicitly set waiting_result_time to 10 minutes
        $tournament->waiting_result_time = 10;
        $tournament->save();

        $tournament = $publishAction->execute($tournament);
        $tournament = $openRegAction->execute($tournament);

        $playerA = $this->createPlayer('playerA@example.com', 'playerA');
        $playerB = $this->createPlayer('playerB@example.com', 'playerB');

        $regA = $registerAction->execute($tournament, $playerA);
        $regB = $registerAction->execute($tournament, $playerB);

        $tournament = $closeRegAction->execute($tournament);
        $tournament = $openCheckinAction->execute($tournament);

        $checkinAction->execute($tournament, $playerA);
        $checkinAction->execute($tournament, $playerB);

        $tournament = $closeCheckinAction->execute($tournament);

        $bracket = $generateBracketAction->execute($tournament);
        $tournament = $startTournamentAction->execute($tournament);

        $round1 = $bracket->rounds()->where('round_number', 1)->first();
        $match = GameMatch::where('round_id', $round1->id)->firstOrFail();

        // AutoStartMatchesListener starts the first match when the tournament starts.
        $this->assertEquals(MatchStatus::IN_PROGRESS, $match->fresh()->status);

        // Player A submits result claiming win
        app(SubmitMatchResultAction::class)->execute($match, $playerA->id, $regA->id, 'I won');
        $match->refresh();

        $this->assertEquals(MatchStatus::WAITING_FOR_CONFIRMATION, $match->status);
        $this->assertFalse($match->isTimedOut());

        // Simulate timeout by modifying result_submitted_at to be older than 10 mins
        $match->result_submitted_at = now()->subMinutes(11);
        $match->save();

        $this->assertTrue($match->fresh()->isTimedOut());

        // Run the AutoForfeitJob
        $job = new AutoForfeitJob();
        $job->handle(app(\App\Modules\Match\Actions\AutoForfeitAction::class));

        $match->refresh();
        $this->assertEquals(MatchStatus::COMPLETED, $match->status);
        $this->assertEquals($regA->id, $match->winner_registration_id);
    }
}
