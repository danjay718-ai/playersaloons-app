<?php

declare(strict_types=1);

namespace Tests\Feature\Match;

use App\Livewire\Match\HeadToHeadList;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Actions\AcceptHeadToHeadChallengeAction;
use App\Modules\Match\Actions\CancelHeadToHeadChallengeAction;
use App\Modules\Match\Actions\ConfirmHeadToHeadResultAction;
use App\Modules\Match\Actions\CreateHeadToHeadChallengeAction;
use App\Modules\Match\Actions\SubmitHeadToHeadResultAction;
use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use App\Shared\Enums\HeadToHeadMatchStatus;
use App\Shared\Enums\LedgerType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class HeadToHeadModuleTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;
    private User $playerA;
    private User $playerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'valorant',
            'is_active' => true,
        ]);

        GameTranslation::query()->create([
            'game_id' => $this->game->id,
            'locale' => 'en',
            'name' => 'Valorant',
        ]);

        $this->playerA = $this->createPlayer('player-a@example.com', 'playerA');
        $this->playerB = $this->createPlayer('player-b@example.com', 'playerB');
    }

    public function test_player_can_create_challenge_and_lock_stake(): void
    {
        $challenge = $this->createChallenge();

        $this->assertEquals(HeadToHeadChallengeStatus::WAITING, $challenge->status);
        $this->assertEquals('90.00', (string) $this->playerA->wallet->fresh()->cached_balance);
        $this->assertDatabaseHas('ledger_entries', [
            'wallet_id' => $this->playerA->wallet->id,
            'reference_type' => HeadToHeadChallenge::class,
            'reference_id' => $challenge->id,
            'type' => LedgerType::H2H_STAKE->value,
            'amount' => '-10.00',
        ]);
    }

    public function test_head_to_head_page_renders_with_real_games(): void
    {
        Livewire::actingAs($this->playerA)
            ->test(HeadToHeadList::class)
            ->assertSee('Initiate Challenge')
            ->assertSee('Valorant');
    }

    public function test_creator_can_cancel_waiting_challenge_and_refund_stake(): void
    {
        $challenge = $this->createChallenge();

        app(CancelHeadToHeadChallengeAction::class)->execute($challenge, $this->playerA);

        $challenge->refresh();
        $this->assertEquals(HeadToHeadChallengeStatus::CANCELLED, $challenge->status);
        $this->assertEquals('100.00', (string) $this->playerA->wallet->fresh()->cached_balance);
    }

    public function test_opponent_can_accept_challenge_and_lock_second_stake(): void
    {
        $challenge = $this->createChallenge();

        $match = app(AcceptHeadToHeadChallengeAction::class)->execute($challenge, $this->playerB, 'PlayerB#222');

        $challenge->refresh();
        $this->assertEquals(HeadToHeadChallengeStatus::MATCHED, $challenge->status);
        $this->assertEquals(HeadToHeadMatchStatus::IN_PROGRESS, $match->status);
        $this->assertEquals('90.00', (string) $this->playerA->wallet->fresh()->cached_balance);
        $this->assertEquals('90.00', (string) $this->playerB->wallet->fresh()->cached_balance);
    }

    public function test_submitted_result_can_be_confirmed_and_paid_out(): void
    {
        $challenge = $this->createChallenge();
        $match = app(AcceptHeadToHeadChallengeAction::class)->execute($challenge, $this->playerB, 'PlayerB#222');

        app(SubmitHeadToHeadResultAction::class)->execute($match, $this->playerA, $this->playerA->id, 'A won 13-8.');
        $match->refresh();

        $this->assertEquals(HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION, $match->status);
        $this->assertEquals($this->playerA->id, $match->winner_user_id);

        app(ConfirmHeadToHeadResultAction::class)->execute($match, $this->playerB);

        $match->refresh();
        $this->assertEquals(HeadToHeadMatchStatus::COMPLETED, $match->status);
        $this->assertEquals('110.00', (string) $this->playerA->wallet->fresh()->cached_balance);
        $this->assertEquals('90.00', (string) $this->playerB->wallet->fresh()->cached_balance);
        $this->assertDatabaseHas('ledger_entries', [
            'wallet_id' => $this->playerA->wallet->id,
            'reference_type' => HeadToHeadMatch::class,
            'reference_id' => $match->id,
            'type' => LedgerType::H2H_PAYOUT->value,
            'amount' => '20.00',
        ]);
    }

    private function createChallenge(): HeadToHeadChallenge
    {
        return app(CreateHeadToHeadChallengeAction::class)->execute($this->playerA, [
            'game_id' => $this->game->id,
            'stake_amount' => 10,
            'creator_game_handle' => 'PlayerA#111',
            'match_timer_minutes' => 30,
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

        Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'cached_balance' => 100.00,
            'status' => 'active',
        ]);

        return $user;
    }
}
