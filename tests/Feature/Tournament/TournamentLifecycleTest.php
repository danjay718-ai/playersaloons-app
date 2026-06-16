<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\ConfirmMatchResultAction;
use App\Modules\Match\Actions\OpenDisputeAction;
use App\Modules\Match\Actions\SubmitMatchResultAction;
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
use App\Modules\Tournament\Exceptions\TournamentFullException;
use App\Modules\Tournament\Exceptions\TournamentNotOpenForRegistrationException;
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\RegistrationStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TournamentLifecycleTest extends TestCase
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
            'email' => 'organizer@example.com',
            'username' => 'organizer',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $this->adminUser->assignRole('SUPER_ADMIN');

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

    private function createPlayer(string $email, string $username, float $balance = 100.0): User
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
                'description' => 'Initial deposit',
                'created_at' => now(),
            ]);
        }

        return $user;
    }

    private function openTournament(float $entryFee = 10.0, int $maxParticipants = 4): array
    {
        $tournament = app(CreateTournamentAction::class)->execute([
            'name' => 'Test Tournament',
            'game_id' => $this->game->id,
            'max_participants' => $maxParticipants,
            'min_participants' => 2,
            'entry_fee' => $entryFee,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        $tournament = app(PublishTournamentAction::class)->execute($tournament);
        $tournament = app(OpenRegistrationAction::class)->execute($tournament);

        return [$tournament];
    }

    // -------------------------------------------------------------------------
    // Registration & Wallet (doc-spec names)
    // -------------------------------------------------------------------------

    public function test_player_can_register_for_tournament_with_sufficient_balance(): void
    {
        [$tournament] = $this->openTournament(entryFee: 10.0);

        $player = $this->createPlayer('player@example.com', 'player', 50.0);
        $registration = app(RegisterForTournamentAction::class)->execute($tournament, $player);

        // Registration created
        $this->assertDatabaseHas('tournament_registrations', [
            'id' => $registration->id,
            'tournament_id' => $tournament->id,
            'user_id' => $player->id,
            'status' => RegistrationStatus::CONFIRMED->value,
        ]);

        // Wallet debited by entry fee
        $this->assertEquals('40.00', $player->wallet->fresh()->cached_balance);

        // ENTRY_FEE ledger entry created
        $this->assertDatabaseHas('ledger_entries', [
            'wallet_id' => $player->wallet->id,
            'type' => LedgerType::ENTRY_FEE->value,
        ]);
    }

    public function test_registration_fails_if_balance_is_insufficient(): void
    {
        [$tournament] = $this->openTournament(entryFee: 50.0);

        $player = $this->createPlayer('broke@example.com', 'broke', 10.0);

        $this->expectException(InsufficientBalanceException::class);

        app(RegisterForTournamentAction::class)->execute($tournament, $player);
    }

    public function test_registration_fails_if_tournament_is_full(): void
    {
        [$tournament] = $this->openTournament(entryFee: 0.0, maxParticipants: 2);

        $p1 = $this->createPlayer('p1@example.com', 'p1', 0.0);
        $p2 = $this->createPlayer('p2@example.com', 'p2', 0.0);
        $p3 = $this->createPlayer('p3@example.com', 'p3', 0.0);

        app(RegisterForTournamentAction::class)->execute($tournament, $p1);
        app(RegisterForTournamentAction::class)->execute($tournament, $p2);

        $this->expectException(TournamentFullException::class);

        app(RegisterForTournamentAction::class)->execute($tournament, $p3);
    }

    // -------------------------------------------------------------------------
    // Match Progression (doc-spec names)
    // -------------------------------------------------------------------------

    private function buildOngoingTournamentWithMatch(): array
    {
        [$tournament] = $this->openTournament(entryFee: 0.0, maxParticipants: 2);

        $pA = $this->createPlayer('pA@example.com', 'pA', 0.0);
        $pB = $this->createPlayer('pB@example.com', 'pB', 0.0);

        $regA = app(RegisterForTournamentAction::class)->execute($tournament, $pA);
        $regB = app(RegisterForTournamentAction::class)->execute($tournament, $pB);

        $tournament = app(CloseRegistrationAction::class)->execute($tournament);
        $tournament = app(OpenCheckinAction::class)->execute($tournament);
        app(CheckinParticipantAction::class)->execute($tournament, $pA);
        app(CheckinParticipantAction::class)->execute($tournament, $pB);
        $tournament = app(CloseCheckinAction::class)->execute($tournament);

        $bracket = app(GenerateBracketAction::class)->execute($tournament);
        $tournament = app(StartTournamentAction::class)->execute($tournament);

        // AutoStartMatchesListener transitions READY -> IN_PROGRESS when tournament starts
        $match = GameMatch::query()->where('status', MatchStatus::IN_PROGRESS)->firstOrFail();

        return [$tournament, $bracket, $match, $pA, $pB, $regA, $regB];
    }

    public function test_match_advances_winner_automatically_after_confirmation(): void
    {
        // 4-player tournament so there is a round 2 to advance into
        $tournament = app(CreateTournamentAction::class)->execute([
            'name' => 'Advancement Test',
            'game_id' => $this->game->id,
            'max_participants' => 4,
            'min_participants' => 4,
            'entry_fee' => 0.0,
            'registration_open_at' => now(),
            'registration_close_at' => now()->addMinutes(30),
        ], $this->adminUser);

        $tournament = app(PublishTournamentAction::class)->execute($tournament);
        $tournament = app(OpenRegistrationAction::class)->execute($tournament);

        $players = [];
        $regs = [];
        for ($i = 1; $i <= 4; $i++) {
            $players[$i] = $this->createPlayer("adv{$i}@example.com", "adv{$i}", 0.0);
            $regs[$i] = app(RegisterForTournamentAction::class)->execute($tournament, $players[$i]);
        }

        $tournament = app(CloseRegistrationAction::class)->execute($tournament);
        $tournament = app(OpenCheckinAction::class)->execute($tournament);
        foreach ($players as $p) {
            app(CheckinParticipantAction::class)->execute($tournament, $p);
        }
        $tournament = app(CloseCheckinAction::class)->execute($tournament);

        $bracket = app(GenerateBracketAction::class)->execute($tournament);
        app(StartTournamentAction::class)->execute($tournament);

        // AutoStartMatchesListener transitions READY -> IN_PROGRESS when tournament starts
        $round1 = $bracket->rounds()->where('round_number', 1)->first();
        $match = GameMatch::query()->where('round_id', $round1->id)->where('status', MatchStatus::IN_PROGRESS)->firstOrFail();

        $regA = $match->playerARegistration;
        $regB = $match->playerBRegistration;
        $userAId = $regA->user_id;
        $userBId = $regB->user_id;

        // Player A submits result, Player B confirms
        app(SubmitMatchResultAction::class)->execute($match, $userAId, $regA->id, 'I won');
        app(ConfirmMatchResultAction::class)->execute($match, $userBId);

        $this->assertEquals(MatchStatus::COMPLETED, $match->fresh()->status);
        $this->assertEquals($regA->id, $match->fresh()->winner_registration_id);

        // Winner (regA) must be seated in round 2's match
        $round2 = $bracket->rounds()->where('round_number', 2)->first();
        $nextMatch = GameMatch::query()->where('round_id', $round2->id)->firstOrFail();

        $this->assertTrue(
            $nextMatch->fresh()->player_a_registration_id === $regA->id
            || $nextMatch->fresh()->player_b_registration_id === $regA->id,
            'Winner was not advanced to the next round match.'
        );
    }

    public function test_match_locks_on_dispute_and_prevents_auto_advancement(): void
    {
        [, , $match, $pA, $pB, $regA] = $this->buildOngoingTournamentWithMatch();

        // Match is already IN_PROGRESS (auto-started by AutoStartMatchesListener on TournamentStarted)

        // Player A submits result
        app(SubmitMatchResultAction::class)->execute($match, $pA->id, $regA->id, 'I won');

        // Player B disputes
        app(OpenDisputeAction::class)->execute($match, $pB->id, 'Screenshot is incorrect.');

        $match->refresh();

        // Match must be DISPUTED — not COMPLETED or advanced
        $this->assertEquals(MatchStatus::DISPUTED, $match->status);
        $this->assertNull($match->winner_registration_id);
    }
}
