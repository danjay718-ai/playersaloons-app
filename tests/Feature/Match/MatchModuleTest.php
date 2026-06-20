<?php

declare(strict_types=1);

namespace Tests\Feature\Match;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\ConfirmMatchResultAction;
use App\Modules\Match\Actions\ForfeitMatchAction;
use App\Modules\Match\Actions\OpenDisputeAction;
use App\Modules\Match\Actions\ResolveDisputeAction;
use App\Modules\Match\Actions\SubmitEvidenceAction;
use App\Modules\Match\Actions\SubmitMatchResultAction;
use App\Modules\Match\Events\TournamentBracketUpdated;
use App\Modules\Match\Jobs\RematchTimeoutJob;
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
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\DisputeResolution;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class MatchModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Game $game;

    private User $playerA;

    private User $playerB;

    private TournamentRegistration $regA;

    private TournamentRegistration $regB;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        // Create Admin
        $this->adminUser = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => 'admin@example.com',
            'username' => 'admin',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('SUPER_ADMIN');

        // Create Game
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

        // Create Players
        $this->playerA = $this->createPlayer('playerA@example.com', 'playerA');
        $this->playerB = $this->createPlayer('playerB@example.com', 'playerB');

        // Setup Tournament & Bracket
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

        $this->tournament = $createAction->execute([
            'name' => 'Championship 2026',
            'game_id' => $this->game->id,
            'max_participants' => 2,
            'min_participants' => 2,
            'entry_fee' => 0.00,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        $this->tournament = $publishAction->execute($this->tournament);
        $this->tournament = $openRegAction->execute($this->tournament);

        $this->regA = $registerAction->execute($this->tournament, $this->playerA);
        $this->regB = $registerAction->execute($this->tournament, $this->playerB);

        $this->tournament = $closeRegAction->execute($this->tournament);
        $this->tournament = $openCheckinAction->execute($this->tournament);

        $checkinAction->execute($this->tournament, $this->playerA);
        $checkinAction->execute($this->tournament, $this->playerB);

        $this->tournament = $closeCheckinAction->execute($this->tournament);

        // Generate Bracket
        $generateBracketAction->execute($this->tournament);

        // Start Tournament (BRACKET_GENERATED -> ONGOING)
        $startTournamentAction->execute($this->tournament);
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

        Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'cached_balance' => 0.00,
            'status' => 'active',
        ]);

        return $user;
    }

    /**
     * Test the successful lifecycle from READY -> IN_PROGRESS -> WAITING_FOR_CONFIRMATION -> COMPLETED.
     */
    public function test_match_lifecycle_flow(): void
    {
        Event::fake([
            TournamentBracketUpdated::class,
        ]);

        $match = GameMatch::query()->where('status', MatchStatus::IN_PROGRESS)->firstOrFail();

        // 1. Match is already IN_PROGRESS (auto-started by AutoStartMatchesListener)

        // 2. Submit Result (Player A claims win)
        $notes = 'We played and I won 16-10.';
        $submission = app(SubmitMatchResultAction::class)->execute(
            $match,
            $this->playerA->id,
            $this->regA->id,
            $notes
        );
        $match->refresh();

        $this->assertEquals(MatchStatus::WAITING_FOR_CONFIRMATION, $match->status);
        $this->assertDatabaseHas('match_result_submissions', [
            'id' => $submission->id,
            'match_id' => $match->id,
            'submitted_by' => $this->playerA->id,
            'winner_registration_id' => $this->regA->id,
            'notes' => $notes,
        ]);

        // Verify submit result notification was sent to opponent B
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->playerB->id,
            'title' => 'Match Result Submitted',
        ]);

        // 3. Confirm Result — must be confirmed by the opponent (playerB), not the submitter
        app(ConfirmMatchResultAction::class)->execute($match, $this->playerB->id);
        $match->refresh();

        $this->assertEquals(MatchStatus::COMPLETED, $match->status);
        $this->assertEquals($this->regA->id, $match->winner_registration_id);
        $this->assertNotNull($match->completed_at);

        // Verify match completed notifications sent to both
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->playerA->id,
            'title' => 'Match Completed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->playerB->id,
            'title' => 'Match Completed',
        ]);

        // Bracket completed, so tournament should be completed
        $this->tournament->refresh();
        $this->assertEquals(TournamentStatus::COMPLETED, $this->tournament->status);

        // Verification of broadcast event
        Event::assertDispatched(TournamentBracketUpdated::class);
    }

    /**
     * Test forfeiting a match from READY.
     */
    public function test_match_forfeit_flow(): void
    {
        Event::fake([
            TournamentBracketUpdated::class,
        ]);

        $match = GameMatch::query()->where('status', MatchStatus::IN_PROGRESS)->firstOrFail();

        // Player A forfeits the match
        app(ForfeitMatchAction::class)->execute($match, $this->regA->id);
        $match->refresh();

        $this->assertEquals(MatchStatus::FORFEITED, $match->status);
        $this->assertEquals($this->regB->id, $match->winner_registration_id); // Opponent B is winner

        // Verify forfeit notification sent to winner B
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->playerB->id,
            'title' => 'Opponent Forfeited',
        ]);

        // Verify bracket update broadcasted and tournament completed
        Event::assertDispatched(TournamentBracketUpdated::class);
        $this->tournament->refresh();
        $this->assertEquals(TournamentStatus::COMPLETED, $this->tournament->status);
    }

    /**
     * Test opening a dispute, uploading evidence, and resolving it to Player B.
     */
    public function test_dispute_resolution_winner_flow(): void
    {
        Storage::fake('public');

        Event::fake([
            TournamentBracketUpdated::class,
        ]);

        $match = GameMatch::query()->where('status', MatchStatus::IN_PROGRESS)->firstOrFail();

        // Match is already IN_PROGRESS — submit a result
        app(SubmitMatchResultAction::class)->execute($match, $this->playerA->id, $this->regA->id);

        // Player B disputes the result
        $dispute = app(OpenDisputeAction::class)->execute($match, $this->playerB->id, 'I disagree with the result.');
        $match->refresh();

        $this->assertEquals(MatchStatus::DISPUTED, $match->status);
        $this->assertEquals(DisputeStatus::OPEN, $dispute->status);

        // Verify dispute notification was created for both
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->playerA->id,
            'title' => 'Match Disputed',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->playerB->id,
            'title' => 'Match Disputed',
        ]);

        // Player B uploads evidence (create mock file without GD dependency)
        $file = UploadedFile::fake()->create('screenshot.jpg', 100, 'image/jpeg');
        $evidence = app(SubmitEvidenceAction::class)->execute($dispute, $this->playerB->id, $file);

        Storage::disk('public')->assertExists($evidence->file_path);
        $dispute->refresh();
        $this->assertEquals(DisputeStatus::UNDER_REVIEW, $dispute->status);

        // Admin resolves dispute in favor of Player B (dynamically determined to handle shuffle)
        $resolution = ($match->player_a_registration_id === $this->regB->id)
            ? DisputeResolution::PLAYER_A
            : DisputeResolution::PLAYER_B;

        app(ResolveDisputeAction::class)->execute($dispute, $this->adminUser, $resolution);
        $match->refresh();
        $dispute->refresh();

        $this->assertEquals(DisputeStatus::RESOLVED, $dispute->status);
        $this->assertEquals($resolution, $dispute->resolution);
        $this->assertEquals(MatchStatus::COMPLETED, $match->status);
        $this->assertEquals($this->regB->id, $match->winner_registration_id);

        // Match completed notifications should be sent
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->playerA->id,
            'title' => 'Match Completed',
        ]);

        Event::assertDispatched(TournamentBracketUpdated::class);
    }

    /**
     * Test resolving a dispute as a rematch.
     */
    public function test_dispute_resolution_rematch_flow(): void
    {
        Event::fake([
            TournamentBracketUpdated::class,
        ]);

        $match = GameMatch::query()->where('status', MatchStatus::IN_PROGRESS)->firstOrFail();

        // Match is already IN_PROGRESS — submit result & open dispute
        app(SubmitMatchResultAction::class)->execute($match, $this->playerA->id, $this->regA->id);
        $dispute = app(OpenDisputeAction::class)->execute($match, $this->playerB->id, 'I disagree with the result.');

        // Admin resolves dispute as a REMATCH
        app(ResolveDisputeAction::class)->execute($dispute, $this->adminUser, DisputeResolution::REMATCH);
        $match->refresh();
        $dispute->refresh();

        $this->assertEquals(DisputeStatus::RESOLVED, $dispute->status);
        $this->assertEquals(DisputeResolution::REMATCH, $dispute->resolution);
        $this->assertEquals(MatchStatus::COMPLETED, $match->status);
        $this->assertNull($match->winner_registration_id); // Original match has no winner

        // Verify rematch match was created in DB
        $this->assertDatabaseHas('matches', [
            'tournament_id' => $match->tournament_id,
            'status' => MatchStatus::READY->value,
            'player_a_registration_id' => $match->player_a_registration_id,
            'player_b_registration_id' => $match->player_b_registration_id,
        ]);

        Event::assertDispatched(TournamentBracketUpdated::class);
    }

    /**
     * Test the RematchTimeoutJob.
     */
    public function test_rematch_timeout_job(): void
    {
        // Setup dispute -> Rematch (must run without faking rematch events so rematch match is created normally)
        $match = GameMatch::query()->where('status', MatchStatus::IN_PROGRESS)->firstOrFail();

        app(SubmitMatchResultAction::class)->execute($match, $this->playerA->id, $this->regA->id);
        $dispute = app(OpenDisputeAction::class)->execute($match, $this->playerB->id, 'I disagree with the result.'); // Player B opened dispute

        app(ResolveDisputeAction::class)->execute($dispute, $this->adminUser, DisputeResolution::REMATCH);

        // Find the rematch match
        $rematch = GameMatch::query()->where('id', '!=', $match->id)->firstOrFail();
        $this->assertEquals(MatchStatus::READY, $rematch->status);

        // Dispatch job: Player B opened dispute, so Player B's registration is forfeited if timeout occurs
        $job = new RematchTimeoutJob($rematch->id, $this->regB->id);
        $job->handle(app(ForfeitMatchAction::class));

        $rematch->refresh();
        $this->assertEquals(MatchStatus::FORFEITED, $rematch->status);
        $this->assertEquals($this->regA->id, $rematch->winner_registration_id); // Player A wins because Player B forfeited
    }

    /**
     * Validate error scenario: cannot submit winner registration that is not in the match.
     */
    public function test_invalid_winner_registration(): void
    {
        $match = GameMatch::query()->where('status', MatchStatus::IN_PROGRESS)->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        // Try submitting a random registration ID (9999) as winner
        app(SubmitMatchResultAction::class)->execute($match, $this->playerA->id, 9999);
    }
}
