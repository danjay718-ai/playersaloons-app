<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Livewire\Tournament\MyTournamentsList;
use App\Livewire\Tournament\PlayerTournamentList;
use App\Livewire\Tournament\TournamentDetail;
use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PlayerTournamentComponentsTest extends TestCase
{
    use RefreshDatabase;

    private User $player;

    private User $opponent;

    private User $admin;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->player = $this->makeUser('PLAYER', 'player@example.com');
        $this->opponent = $this->makeUser('PLAYER', 'opponent@example.com');
        $this->admin = $this->makeUser('ADMIN', 'admin@example.com');
        $this->game = $this->makeGame('arena', 'Arena');
    }

    public function test_elimination_modal_shows_on_lost_match(): void
    {
        $tournament = $this->makeTournament('Elimination Cup', TournamentStatus::ONGOING);
        [$playerRegistration, $opponentRegistration] = $this->registerPlayers($tournament);
        $this->makeMatch($tournament, $playerRegistration, $opponentRegistration, $opponentRegistration);

        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSeeHtml('hasLost: true')
            ->assertSeeHtml("value === 'bracket' && hasLost && !acknowledgedElimination")
            ->assertSee('Eliminated');
    }

    public function test_elimination_modal_does_not_show_if_not_lost(): void
    {
        $tournament = $this->makeTournament('Active Cup', TournamentStatus::ONGOING);
        $this->registerPlayers($tournament);

        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSeeHtml('hasLost: false');
    }

    public function test_elimination_modal_go_back_resets_tab(): void
    {
        $tournament = $this->makeTournament('Go Back Cup', TournamentStatus::ONGOING);
        $this->registerPlayers($tournament);

        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSeeHtml("@click=\"activeTab = 'overview'; showEliminationModal = false;\"");
    }

    public function test_elimination_modal_continue_stays_on_matches(): void
    {
        $tournament = $this->makeTournament('Continue Cup', TournamentStatus::ONGOING);
        $this->registerPlayers($tournament);

        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSeeHtml('@click="acknowledgedElimination = true; showEliminationModal = false;"')
            ->assertDontSeeHtml("acknowledgedElimination = true; activeTab = 'overview'");
    }

    public function test_stats_banner_calculation(): void
    {
        $active = $this->makeTournament('Active Stats Cup', TournamentStatus::ONGOING);
        [$activePlayer, $activeOpponent] = $this->registerPlayers($active);
        $this->makeMatch($active, $activePlayer, $activeOpponent, $activePlayer);

        $lost = $this->makeTournament('Lost Stats Cup', TournamentStatus::ONGOING);
        [$lostPlayer, $lostOpponent] = $this->registerPlayers($lost);
        $this->makeMatch($lost, $lostPlayer, $lostOpponent, $lostOpponent);

        $completed = $this->makeTournament('Completed Stats Cup', TournamentStatus::COMPLETED);
        $this->registerPlayers($completed);

        Livewire::actingAs($this->player)
            ->test(MyTournamentsList::class)
            ->assertViewHas('activeCount', 1)
            ->assertViewHas('historyCount', 2)
            ->assertViewHas('matchWins', 1)
            ->assertViewHas('matchLosses', 1);
    }

    public function test_elimination_shifts_tournament_to_history(): void
    {
        $tournament = $this->makeTournament('Recently Lost Cup', TournamentStatus::ONGOING);
        [$playerRegistration, $opponentRegistration] = $this->registerPlayers($tournament);
        $this->makeMatch($tournament, $playerRegistration, $opponentRegistration, $opponentRegistration);

        Livewire::actingAs($this->player)
            ->test(MyTournamentsList::class)
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 0)
            ->set('tSubTab', 'history')
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->contains($tournament->id));
    }

    public function test_n_plus_one_query_prevention(): void
    {
        foreach (range(1, 3) as $number) {
            $tournament = $this->makeTournament("History Cup {$number}", TournamentStatus::COMPLETED);
            [$playerRegistration, $opponentRegistration] = $this->registerPlayers($tournament);
            $this->makeMatch($tournament, $playerRegistration, $opponentRegistration, $playerRegistration);
        }

        $matchQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$matchQueries): void {
            if (str_contains(strtolower($query->sql), 'from "matches"')) {
                $matchQueries++;
            }
        });

        Livewire::actingAs($this->player)
            ->test(MyTournamentsList::class)
            ->set('tSubTab', 'history')
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 3);

        $this->assertSame(3, $matchQueries, 'Match query count should remain constant as tournament cards increase.');
    }

    public function test_player_tournament_list_filtering(): void
    {
        $otherGame = $this->makeGame('racing', 'Racing');
        $daily = $this->makeTournament('Daily Arena Clash', TournamentStatus::REGISTRATION_OPEN, 'daily');
        $weekly = $this->makeTournament('Weekly Arena Open', TournamentStatus::ONGOING, 'weekly');
        $other = $this->makeTournament('Weekly Racing Clash', TournamentStatus::REGISTRATION_OPEN, 'weekly', $otherGame);
        $this->makeTournament('Hidden Completed Clash', TournamentStatus::COMPLETED, 'daily');

        Livewire::actingAs($this->player)
            ->test(PlayerTournamentList::class)
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 3)
            ->set('search', 'Clash')
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->sort()->values()->all() === collect([$daily->id, $other->id])->sort()->values()->all())
            ->set('gameId', (string) $otherGame->id)
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->all() === [$other->id])
            ->set('search', '')
            ->set('activeTab', 'weekly')
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->all() === [$other->id])
            ->set('gameId', '')
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->sort()->values()->all() === collect([$weekly->id, $other->id])->sort()->values()->all());
    }

    private function makeUser(string $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'username' => Str::before($email, '@'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function makeGame(string $slug, string $name): Game
    {
        $game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => $slug,
            'is_active' => true,
        ]);
        $game->translations()->create([
            'locale' => 'en',
            'name' => $name,
            'description' => $name,
        ]);

        return $game;
    }

    private function makeTournament(
        string $name,
        TournamentStatus $status,
        string $frequency = 'daily',
        ?Game $game = null,
    ): Tournament {
        return Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => ($game ?? $this->game)->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => $status,
            'frequency' => $frequency,
            'entry_fee' => 0,
            'prize_pool' => 0,
            'max_participants' => 8,
            'min_participants' => 2,
            'completed_at' => $status === TournamentStatus::COMPLETED ? now() : null,
            'created_by' => $this->admin->id,
        ]);
    }

    /** @return array{TournamentRegistration, TournamentRegistration} */
    private function registerPlayers(Tournament $tournament): array
    {
        return [
            $this->register($tournament, $this->player),
            $this->register($tournament, $this->opponent),
        ];
    }

    private function register(Tournament $tournament, User $user): TournamentRegistration
    {
        return $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);
    }

    private function makeMatch(
        Tournament $tournament,
        TournamentRegistration $playerRegistration,
        TournamentRegistration $opponentRegistration,
        TournamentRegistration $winner,
    ): GameMatch {
        $bracket = Bracket::query()->create(['tournament_id' => $tournament->id]);
        $round = Round::query()->create([
            'bracket_id' => $bracket->id,
            'round_number' => 1,
        ]);

        return GameMatch::query()->create([
            'uuid' => Str::uuid()->toString(),
            'tournament_id' => $tournament->id,
            'round_id' => $round->id,
            'player_a_registration_id' => $playerRegistration->id,
            'player_b_registration_id' => $opponentRegistration->id,
            'winner_registration_id' => $winner->id,
            'status' => MatchStatus::COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
