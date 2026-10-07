<?php

declare(strict_types=1);

namespace Tests\Unit\Tournament\Services;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Listeners\AdvanceWinnerListener;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Actions\CompleteTournamentAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentParticipant;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Services\BracketGenerationService;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BracketGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private Game $game;

    private BracketGenerationService $service;

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

        $this->service = app(BracketGenerationService::class);
    }

    /**
     * Helper to set up a tournament and checked-in participants.
     */
    private function setupTournamentWithParticipants(int $count, int $capacity = 16): Tournament
    {
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => "Tournament {$count} Players",
            'slug' => "tournament-{$count}-".Str::random(6),
            'game_id' => $this->game->id,
            'max_participants' => $capacity,
            'min_participants' => 2,
            'entry_fee' => 0.00,
            'status' => 'DRAFT',
            'created_by' => $this->adminUser->id,
        ]);

        for ($i = 1; $i <= $count; $i++) {
            $player = User::query()->create([
                'uuid' => Str::uuid()->toString(),
                'email' => "p{$i}_{$count}@example.com",
                'username' => "player{$i}_{$count}",
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]);
            $player->assignRole('PLAYER');

            $reg = TournamentRegistration::query()->create([
                'uuid' => Str::uuid()->toString(),
                'tournament_id' => $tournament->id,
                'user_id' => $player->id,
                'status' => RegistrationStatus::CONFIRMED,
                'payment_status' => PaymentStatus::FREE,
                'registered_at' => now(),
            ]);

            TournamentParticipant::query()->create([
                'tournament_id' => $tournament->id,
                'registration_id' => $reg->id,
                'user_id' => $player->id,
                'seed' => $i,
                'status' => 'checked_in',
            ]);
        }

        return $tournament;
    }

    public function test_bracket_generation_with_2_players(): void
    {
        $tournament = $this->setupTournamentWithParticipants(2);

        $bracket = $this->service->generate($tournament);

        $this->assertNotNull($bracket);
        $this->assertCount(1, $bracket->rounds); // log2(2) = 1 round

        $round1 = $bracket->rounds()->where('round_number', 1)->first();
        $matches = GameMatch::where('round_id', $round1->id)->get();

        $this->assertCount(1, $matches);
        $this->assertEquals(MatchStatus::READY, $matches[0]->status);
        $this->assertNotNull($matches[0]->player_a_registration_id);
        $this->assertNotNull($matches[0]->player_b_registration_id);
        $this->assertNull($matches[0]->winner_registration_id);
    }

    public function test_bracket_generation_with_5_players(): void
    {
        $bracket = $this->service->generate($this->setupTournamentWithParticipants(5));
        $rounds = $bracket->rounds()->orderBy('round_number')->get();
        $this->assertSame([3, 2, 1], $rounds->map(fn ($round) => $round->matches()->count())->all());
        $matches = $rounds[0]->matches()->orderBy('id')->get();
        $this->assertCount(2, $matches->where('status', MatchStatus::READY));
        $this->assertCount(1, $matches->where('status', MatchStatus::COMPLETED));
        $bye = $matches->last();
        $this->assertNull($bye->player_b_registration_id);
        $this->assertSame($bye->player_a_registration_id, $bye->winner_registration_id);

        // The unpaired winner also receives the only bye among three R2 players.
        $secondRoundBye = $rounds[1]->matches()->orderBy('id')->get()->last();
        $this->assertSame(MatchStatus::COMPLETED, $secondRoundBye->status);
        $this->assertSame($bye->winner_registration_id, $secondRoundBye->winner_registration_id);
        $final = $rounds[2]->matches()->firstOrFail();
        $this->assertSame(MatchStatus::PENDING, $final->status);
        $this->assertSame($bye->winner_registration_id, $final->player_b_registration_id);
    }

    public function test_bracket_generation_with_6_players(): void
    {
        $bracket = $this->service->generate($this->setupTournamentWithParticipants(6));
        $rounds = $bracket->rounds()->orderBy('round_number')->get();
        $this->assertSame([3, 2, 1], $rounds->map(fn ($round) => $round->matches()->count())->all());
        $this->assertCount(3, $rounds[0]->matches()->get()->where('status', MatchStatus::READY));
        $this->assertCount(0, $rounds[0]->matches()->get()->where('status', MatchStatus::COMPLETED));

        // The last R1 winner is unpaired in R2 and must advance to the final.
        $match = $rounds[0]->matches()->orderBy('id')->get()->last();
        $match->update(['status' => MatchStatus::COMPLETED, 'winner_registration_id' => $match->player_a_registration_id]);
        app(AdvanceWinnerListener::class)->handle((object) ['matchId' => $match->id]);
        $bye = $rounds[1]->matches()->orderBy('id')->get()->last();
        $this->assertSame(MatchStatus::COMPLETED, $bye->status);
        $this->assertSame($match->winner_registration_id, $bye->winner_registration_id);
        $this->assertSame($match->winner_registration_id, $rounds[2]->matches()->firstOrFail()->player_b_registration_id);
    }

    public function test_nine_players_in_sixteen_slots_get_four_pairs_and_one_bye(): void
    {
        $tournament = $this->setupTournamentWithParticipants(9);
        $bracket = $this->service->generate($tournament);
        $rounds = $bracket->rounds()->orderBy('round_number')->get();
        $this->assertSame([5, 3, 2, 1], $rounds->map(fn ($round) => $round->matches()->count())->all());
        $matches = $rounds[0]->matches()->get();
        $this->assertCount(4, $matches->where('status', MatchStatus::READY));
        $this->assertCount(1, $matches->where('status', MatchStatus::COMPLETED));
        $players = $matches->flatMap(fn ($match) => [$match->player_a_registration_id, $match->player_b_registration_id])->filter();
        $this->assertCount(9, $players);
        $this->assertCount(9, $players->unique());
    }

    #[DataProvider('largeTournamentPairingCounts')]
    public function test_legacy_advancement_pairs_all_players_in_every_round(int $capacity, int $count): void
    {
        $tournament = $this->setupTournamentWithParticipants($count, $capacity);
        $bracket = $this->service->generate($tournament);
        $this->mock(CompleteTournamentAction::class)->shouldReceive('execute')->once();
        $listener = app(AdvanceWinnerListener::class);
        $expectedRegistrations = $tournament->participants()->pluck('registration_id')->all();

        foreach ($bracket->rounds()->orderBy('round_number')->get() as $round) {
            $matches = $round->matches()->orderBy('id')->get();
            $playerCount = count($expectedRegistrations);
            $this->assertCount((int) ceil($playerCount / 2), $matches);
            $this->assertCount(intdiv($playerCount, 2), $matches->where('status', MatchStatus::READY));
            $this->assertCount($playerCount % 2, $matches->where('status', MatchStatus::COMPLETED));
            $players = $matches->flatMap(fn ($match) => [$match->player_a_registration_id, $match->player_b_registration_id])->filter();
            $this->assertCount($playerCount, $players);
            $this->assertEqualsCanonicalizing($expectedRegistrations, $players->all());
            foreach ($matches->where('status', MatchStatus::COMPLETED) as $bye) {
                $this->assertNull($bye->player_b_registration_id);
                $this->assertSame($bye->player_a_registration_id, $bye->winner_registration_id);
            }
            foreach ($matches->where('status', MatchStatus::READY)->reverse() as $pair) {
                $this->assertNotNull($pair->player_a_registration_id);
                $this->assertNotNull($pair->player_b_registration_id);
                $pair->update(['status' => MatchStatus::COMPLETED, 'winner_registration_id' => $pair->player_a_registration_id]);
                $listener->handle((object) ['matchId' => $pair->id]);
            }
            $expectedRegistrations = $round->matches()->pluck('winner_registration_id')->all();
        }
    }

    public static function largeTournamentPairingCounts(): array
    {
        $cases = [];
        foreach ([16, 32, 64, 128] as $capacity) {
            foreach ([$capacity, $capacity - 1, $capacity - 2, intdiv($capacity, 2) + 1, intdiv($capacity, 2) + 2] as $count) {
                $cases["{$count} players in {$capacity} slots"] = [$capacity, $count];
            }
        }

        return $cases;
    }

    public function test_bracket_generation_with_8_players(): void
    {
        $tournament = $this->setupTournamentWithParticipants(8);

        $bracket = $this->service->generate($tournament);

        $this->assertNotNull($bracket);
        $this->assertCount(3, $bracket->rounds); // log2(8) = 3 rounds

        $round1 = $bracket->rounds()->where('round_number', 1)->first();
        $matches = GameMatch::where('round_id', $round1->id)->orderBy('id')->get();

        // nextPowerOfTwo(8) = 8.
        // byes = 8 - 8 = 0.
        // actual matches = 4.
        $this->assertCount(4, $matches);

        $readyMatches = $matches->where('status', MatchStatus::READY);
        $this->assertCount(4, $readyMatches);

        // Verify propagation to round 2: all pending
        $round2 = $bracket->rounds()->where('round_number', 2)->first();
        $matchesRound2 = GameMatch::where('round_id', $round2->id)->orderBy('id')->get();
        $this->assertCount(2, $matchesRound2);

        foreach ($matchesRound2 as $match) {
            $this->assertNull($match->player_a_registration_id);
            $this->assertNull($match->player_b_registration_id);
            $this->assertEquals(MatchStatus::PENDING, $match->status);
        }
    }
}
