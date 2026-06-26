<?php

declare(strict_types=1);

namespace Tests\Feature\Tournament;

use App\Livewire\Tournament\PlayerTournamentList;
use App\Livewire\Tournament\TournamentDetail;
use App\Modules\CMS\Models\Game;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use App\Modules\Wallet\Models\Wallet;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TournamentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $player;
    private User $admin;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        $this->player = $this->makeUser('PLAYER', 'player@example.com');
        $this->admin  = $this->makeUser('ADMIN', 'admin@example.com');

        $this->game = Game::query()->create([
            'uuid'      => Str::uuid()->toString(),
            'slug'      => 'test-game',
            'is_active' => true,
        ]);
        $this->game->translations()->create([
            'locale'      => 'en',
            'name'        => 'Test Game',
            'description' => 'desc',
        ]);
    }

    private function makeUser(string $role, string $email): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid'              => Str::uuid()->toString(),
            'email'             => $email,
            'username'          => explode('@', $email)[0],
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
            'status'            => UserStatus::ACTIVE,
        ]);
        $user->assignRole($role);

        Wallet::query()->create([
            'uuid'           => Str::uuid()->toString(),
            'user_id'        => $user->id,
            'cached_balance' => '0.00',
            'status'         => WalletStatus::ACTIVE,
        ]);

        return $user;
    }

    private function makeTournament(TournamentStatus $status): Tournament
    {
        return Tournament::query()->create([
            'uuid'             => Str::uuid()->toString(),
            'game_id'          => $this->game->id,
            'name'             => 'Test Cup',
            'slug'             => 'test-cup-' . Str::random(4),
            'status'           => $status,
            'entry_fee'        => '0.00',
            'prize_pool'       => '0.00',
            'max_participants' => 8,
            'min_participants' => 2,
            'created_by'       => $this->admin->id,
        ]);
    }

    /**
     * Only PLAYER role sees the Join Tournament button.
     * Admins and non-players see the "Only players can join" message instead.
     */
    public function test_join_tournament_button_is_restricted_by_role(): void
    {
        $tournament = $this->makeTournament(TournamentStatus::REGISTRATION_OPEN);

        // PLAYER sees the join button
        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSee('Join Tournament');

        // ADMIN does not see join button — sees restriction message instead
        Livewire::actingAs($this->admin)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertDontSee('Join Tournament');
    }

    /**
     * Player tournament listing only shows active/joinable statuses.
     * DRAFT, CANCELLED, COMPLETED tournaments must be excluded.
     */
    public function test_tournament_listing_filters_by_status(): void
    {
        $this->makeTournament(TournamentStatus::REGISTRATION_OPEN);  // visible
        $this->makeTournament(TournamentStatus::ONGOING);             // visible
        $this->makeTournament(TournamentStatus::DRAFT);               // hidden
        $this->makeTournament(TournamentStatus::CANCELLED);           // hidden
        $this->makeTournament(TournamentStatus::COMPLETED);           // hidden

        Livewire::actingAs($this->player)
            ->test(PlayerTournamentList::class)
            ->assertViewHas('tournaments', function ($paginator) {
                $statuses = $paginator->getCollection()->pluck('status');
                $hidden = [TournamentStatus::DRAFT, TournamentStatus::CANCELLED, TournamentStatus::COMPLETED];

                foreach ($hidden as $s) {
                    if ($statuses->contains($s)) {
                        return false;
                    }
                }

                return $paginator->total() === 2;
            });
    }

    /**
     * Completed tournaments are hidden from browse, but players can still open
     * their history detail pages. Draft tournaments remain unavailable.
     */
    public function test_player_can_view_completed_tournament_detail_from_history(): void
    {
        $completedTournament = $this->makeTournament(TournamentStatus::COMPLETED);
        $completedTournament->update(['name' => 'Completed History Cup']);

        $draftTournament = $this->makeTournament(TournamentStatus::DRAFT);
        $draftTournament->update(['name' => 'Draft Hidden Cup']);

        Livewire::actingAs($this->player)
            ->test(TournamentDetail::class, ['uuid' => $completedTournament->uuid])
            ->assertSee('Completed History Cup');

        $this->actingAs($this->player)
            ->get('/tournaments/'.$draftTournament->uuid.'/view')
            ->assertNotFound();
    }

    /**
     * Matches, Players, and Activity tabs are disabled for non-participants.
     * A registered player can see them; a non-registered player cannot.
     */
    public function test_view_restricted_details_policy(): void
    {
        $tournament = $this->makeTournament(TournamentStatus::REGISTRATION_OPEN);

        $nonParticipant = $this->makeUser('PLAYER', 'outsider@example.com');
        $participant    = $this->makeUser('PLAYER', 'insider@example.com');

        // Register insider
        $tournament->registrations()->create([
            'uuid'           => Str::uuid()->toString(),
            'user_id'        => $participant->id,
            'status'         => 'confirmed',
            'payment_status' => 'free',
            'registered_at'  => now(),
        ]);

        // Non-participant: tabs should be disabled (cursor-not-allowed)
        Livewire::actingAs($nonParticipant)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSee('cursor-not-allowed');

        // Participant: tabs are clickable, no cursor-not-allowed for their tabs
        // Gate resolves true → @can block renders the clickable button
        Livewire::actingAs($participant)
            ->test(TournamentDetail::class, ['uuid' => $tournament->uuid])
            ->assertSeeHtml("@click=\"activeTab = 'participants'\"");
    }
}
