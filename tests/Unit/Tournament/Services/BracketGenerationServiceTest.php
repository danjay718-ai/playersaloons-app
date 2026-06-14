<?php

declare(strict_types=1);

namespace Tests\Unit\Tournament\Services;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
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
    private function setupTournamentWithParticipants(int $count): Tournament
    {
        $tournament = Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => "Tournament {$count} Players",
            'slug' => "tournament-{$count}-" . Str::random(6),
            'game_id' => $this->game->id,
            'max_participants' => 16,
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
        $tournament = $this->setupTournamentWithParticipants(5);

        $bracket = $this->service->generate($tournament);

        $this->assertNotNull($bracket);
        $this->assertCount(3, $bracket->rounds); // log2(8) = 3 rounds

        $round1 = $bracket->rounds()->where('round_number', 1)->first();
        $matches = GameMatch::where('round_id', $round1->id)->orderBy('id')->get();

        // nextPowerOfTwo(5) = 8.
        // byes = 8 - 5 = 3.
        // actual matches = (5 - 3) / 2 = 1.
        $this->assertCount(4, $matches);
        
        $readyMatches = $matches->where('status', MatchStatus::READY);
        $completedMatches = $matches->where('status', MatchStatus::COMPLETED);

        $this->assertCount(1, $readyMatches);
        $this->assertCount(3, $completedMatches);

        foreach ($completedMatches as $match) {
            $this->assertNotNull($match->player_a_registration_id);
            $this->assertNull($match->player_b_registration_id);
            $this->assertEquals($match->player_a_registration_id, $match->winner_registration_id);
        }

        // Verify propagation to round 2
        $round2 = $bracket->rounds()->where('round_number', 2)->first();
        $matchesRound2 = GameMatch::where('round_id', $round2->id)->orderBy('id')->get();
        $this->assertCount(2, $matchesRound2);

        $r2Match1 = $matchesRound2[0];
        $r2Match2 = $matchesRound2[1];

        // Match 1 has one participant from bye, other waiting for round 1 match 1
        $this->assertNotNull($r2Match1->player_b_registration_id);
        $this->assertNull($r2Match1->player_a_registration_id);
        $this->assertEquals(MatchStatus::PENDING, $r2Match1->status);

        // Match 2 has both participants from byes (round 1 matches 3 and 4)
        $this->assertNotNull($r2Match2->player_a_registration_id);
        $this->assertNotNull($r2Match2->player_b_registration_id);
        $this->assertEquals(MatchStatus::READY, $r2Match2->status);
    }

    public function test_bracket_generation_with_6_players(): void
    {
        $tournament = $this->setupTournamentWithParticipants(6);

        $bracket = $this->service->generate($tournament);

        $this->assertNotNull($bracket);
        $this->assertCount(3, $bracket->rounds); // log2(8) = 3 rounds

        $round1 = $bracket->rounds()->where('round_number', 1)->first();
        $matches = GameMatch::where('round_id', $round1->id)->orderBy('id')->get();

        // nextPowerOfTwo(6) = 8.
        // byes = 8 - 6 = 2.
        // actual matches = (6 - 2) / 2 = 2.
        $this->assertCount(4, $matches);
        
        $readyMatches = $matches->where('status', MatchStatus::READY);
        $completedMatches = $matches->where('status', MatchStatus::COMPLETED);

        $this->assertCount(2, $readyMatches);
        $this->assertCount(2, $completedMatches);

        foreach ($completedMatches as $match) {
            $this->assertNotNull($match->player_a_registration_id);
            $this->assertNull($match->player_b_registration_id);
            $this->assertEquals($match->player_a_registration_id, $match->winner_registration_id);
        }

        // Verify propagation to round 2
        $round2 = $bracket->rounds()->where('round_number', 2)->first();
        $matchesRound2 = GameMatch::where('round_id', $round2->id)->orderBy('id')->get();
        $this->assertCount(2, $matchesRound2);

        $r2Match1 = $matchesRound2[0];
        $r2Match2 = $matchesRound2[1];

        // Round 2 Match 1 is pending winners from Match 1 and Match 2
        $this->assertNull($r2Match1->player_a_registration_id);
        $this->assertNull($r2Match1->player_b_registration_id);
        $this->assertEquals(MatchStatus::PENDING, $r2Match1->status);

        // Round 2 Match 2 is READY because both Match 3 and Match 4 are byes (completed)
        $this->assertNotNull($r2Match2->player_a_registration_id);
        $this->assertNotNull($r2Match2->player_b_registration_id);
        $this->assertEquals(MatchStatus::READY, $r2Match2->status);
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
