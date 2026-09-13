<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Livewire\Game\GameShow;
use App\Livewire\Tournament\MyTournamentsList;
use App\Livewire\Tournament\PlayerTournamentList;
use App\Livewire\Tournament\TournamentDetail;
use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\PlayerExperienceAward;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Models\Bracket;
use App\Modules\Tournament\Models\Round;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use App\Shared\Enums\HeadToHeadMatchStatus;
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

        // These fixtures deliberately exercise the retained V1 list/query
        // path. Do not inherit a developer's local V2 feature flag.
        config()->set('features.tournament_v2.enabled', false);

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
        PlayerExperienceAward::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->player->id,
            'source_type' => 'tournament',
            'source_id' => $tournament->id,
            'reason' => 'participation',
            'amount' => 10,
        ]);

        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSeeHtml('hasLost: true')
            ->assertSeeHtml("value === 'bracket' && hasLost && !acknowledgedElimination")
            ->assertSee('Eliminated')
            ->assertSee('Defeated')
            ->assertSee('+10 XP earned')
            ->assertDontSee('Reservation Confirmed')
            ->assertDontSee('Cancellation Details');
    }

    public function test_game_stream_tab_does_not_render_seeded_sample_trailers(): void
    {
        StreamChannel::query()->create([
            'game_id' => $this->game->id,
            'provider' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=sample123',
            'title' => 'Sample trailer that must stay hidden',
            'is_public' => true,
            'metadata' => ['kind' => 'sample_game_trailer'],
        ]);

        Livewire::test(GameShow::class, ['game' => $this->game])
            ->set('activeTab', 'streams')
            ->assertDontSee('Sample trailer that must stay hidden')
            ->assertSee('No public streams for this game yet.');
    }

    public function test_elimination_modal_does_not_show_if_not_lost(): void
    {
        $tournament = $this->makeTournament('Active Cup', TournamentStatus::ONGOING);
        $this->registerPlayers($tournament);

        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSeeHtml('hasLost: false');
    }

    public function test_ongoing_participant_sees_direct_match_room_action(): void
    {
        $tournament = $this->makeTournament('Live Head to Head', TournamentStatus::ONGOING);
        [$playerRegistration, $opponentRegistration] = $this->registerPlayers($tournament);
        $bracket = Bracket::query()->create(['tournament_id' => $tournament->id]);
        $round = Round::query()->create(['bracket_id' => $bracket->id, 'round_number' => 1]);
        $match = GameMatch::query()->create([
            'uuid' => Str::uuid()->toString(),
            'tournament_id' => $tournament->id,
            'round_id' => $round->id,
            'player_a_registration_id' => $playerRegistration->id,
            'player_b_registration_id' => $opponentRegistration->id,
            'status' => MatchStatus::IN_PROGRESS,
            'started_at' => now(),
        ]);

        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSee('Open Match Room & Report Result')
            ->assertSee("/matches/{$match->uuid}", escape: false);
    }

    public function test_bracket_displays_completed_match_as_done(): void
    {
        $tournament = $this->makeTournament('Completed Bracket Match', TournamentStatus::ONGOING);
        [$playerRegistration, $opponentRegistration] = $this->registerPlayers($tournament);
        $this->makeMatch($tournament, $playerRegistration, $opponentRegistration, $playerRegistration);

        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->call('loadSection', 'bracket')
            ->assertSee('Done')
            ->assertDontSee('Fin.');
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
            ->assertViewHas('historyMatches', fn ($items) => $items->contains(fn ($match) => $match['type'] === 'tournament' && $match['tournament'] === $tournament->name));
    }

    public function test_history_combines_tournament_and_head_to_head_matches(): void
    {
        $tournament = $this->makeTournament('Mixed Match Cup', TournamentStatus::COMPLETED);
        [$playerRegistration, $opponentRegistration] = $this->registerPlayers($tournament);
        $this->makeMatch($tournament, $playerRegistration, $opponentRegistration, $playerRegistration);
        $challenge = HeadToHeadChallenge::query()->create([
            'uuid' => Str::uuid()->toString(),
            'creator_user_id' => $this->player->id,
            'game_id' => $this->game->id,
            'stake_amount' => 10,
            'status' => HeadToHeadChallengeStatus::MATCHED,
            'creator_game_handle' => 'player-one',
            'matched_at' => now()->subHour(),
        ]);
        HeadToHeadMatch::query()->create([
            'uuid' => Str::uuid()->toString(),
            'challenge_id' => $challenge->id,
            'creator_user_id' => $this->player->id,
            'opponent_user_id' => $this->opponent->id,
            'game_id' => $this->game->id,
            'stake_amount' => 10,
            'status' => HeadToHeadMatchStatus::COMPLETED,
            'creator_game_handle' => 'player-one',
            'opponent_game_handle' => 'player-two',
            'winner_user_id' => $this->opponent->id,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        Livewire::actingAs($this->player)
            ->test(MyTournamentsList::class)
            ->set('tSubTab', 'history')
            ->assertViewHas('historyMatches', fn ($items) => $items->pluck('type')->sort()->values()->all() === ['head_to_head', 'tournament'])
            ->assertSee('Head to Head')
            ->assertSee('Tournament');
    }

    public function test_n_plus_one_query_prevention(): void
    {
        foreach (range(1, 3) as $number) {
            $tournament = $this->makeTournament("History Cup {$number}", TournamentStatus::COMPLETED);
            [$playerRegistration, $opponentRegistration] = $this->registerPlayers($tournament);
            $this->makeMatch($tournament, $playerRegistration, $opponentRegistration, $playerRegistration);
        }

        $component = Livewire::actingAs($this->player)->test(MyTournamentsList::class);
        $matchQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$matchQueries): void {
            if (str_contains(strtolower($query->sql), 'from "matches"')) {
                $matchQueries++;
            }
        });

        $component->set('tSubTab', 'history')
            ->assertViewHas('historyMatches', fn ($items) => $items->total() === 3);

        $this->assertLessThanOrEqual(5, $matchQueries, 'Match query count should remain constant as match history grows.');
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
            ->assertViewHas('tournaments', fn ($items) => $items->total() === 2)
            ->set('search', 'Clash')
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->sort()->values()->all() === collect([$daily->id, $other->id])->sort()->values()->all())
            ->set('gameId', (string) $otherGame->id)
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->all() === [$other->id])
            ->set('search', '')
            ->set('frequency', 'weekly')
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->all() === [$other->id])
            ->set('gameId', '')
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->all() === [$other->id])
            ->set('activeTab', 'ongoing')
            ->assertViewHas('tournaments', fn ($items) => $items->pluck('id')->all() === [$weekly->id]);
    }

    public function test_discovery_orders_games_and_game_page_keeps_competitions_in_browse_only(): void
    {
        $otherGame = $this->makeGame('racing', 'Racing');
        $featured = $this->makeTournament('Featured Arena Cup', TournamentStatus::REGISTRATION_OPEN);
        $featured->update(['is_featured' => true]);
        $this->makeTournament('Arena Live Cup', TournamentStatus::ONGOING);
        $this->makeTournament('Racing Open', TournamentStatus::REGISTRATION_OPEN, 'daily', $otherGame);

        Livewire::test(PlayerTournamentList::class)
            ->assertViewHas('popularGames', fn ($games) => $games->first()->is($this->game))
            ->assertSee('Previous games')
            ->assertSee('Next games')
            ->assertSee(route('games.show', $this->game), escape: false);

        Livewire::test(GameShow::class, ['game' => $this->game])
            ->assertSee('Arena')
            ->assertSee('Overview')
            ->assertSee('Browse Tournaments')
            ->assertSee('Streams')
            ->assertDontSee('Featured Arena Cup')
            ->set('activeTab', 'browse')
            ->assertSee('Featured Arena Cup');
    }

    public function test_game_overview_counts_only_upcoming_and_unexpired_ongoing_competitions(): void
    {
        $upcoming = $this->makeTournament('Upcoming Arena Cup', TournamentStatus::REGISTRATION_OPEN);
        $upcoming->update(['start_at' => now()->addHour(), 'end_at' => now()->addHours(2)]);

        $ongoing = $this->makeTournament('Ongoing Arena Cup', TournamentStatus::ONGOING);
        $ongoing->update(['start_at' => now()->subHour(), 'end_at' => now()->addHour()]);

        $expiredOpen = $this->makeTournament('Expired Open Cup', TournamentStatus::REGISTRATION_OPEN);
        $expiredOpen->update(['start_at' => now()->subHours(2), 'end_at' => now()->subHour()]);

        $expiredOngoing = $this->makeTournament('Expired Ongoing Cup', TournamentStatus::ONGOING);
        $expiredOngoing->update(['start_at' => now()->subHours(2), 'end_at' => now()->subHour()]);

        $this->makeTournament('Completed Arena Cup', TournamentStatus::COMPLETED);

        Livewire::test(GameShow::class, ['game' => $this->game])
            ->assertViewHas('activeCompetitionCount', 2)
            ->assertSee('Upcoming and ongoing tournaments.');
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
