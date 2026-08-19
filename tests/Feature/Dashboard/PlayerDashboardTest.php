<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Livewire\Dashboard\PlayerDashboard;
use App\Modules\CMS\Models\Game;
use App\Modules\Community\Models\BroadcastMessage;
use App\Modules\Community\Models\ChatConversation;
use App\Modules\Community\Models\ChatMessage;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PlayerProgressionService;
use App\Modules\Tournament\Events\TournamentCompleted;
use App\Modules\Tournament\Listeners\AwardTournamentExperienceListener;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PlayerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $player;

    private User $admin;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->player = User::factory()->create(['username' => 'dashboard_player']);
        $this->player->assignRole('PLAYER');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('ADMIN');
        $this->game = Game::query()->create(['uuid' => Str::uuid()->toString(), 'slug' => 'dashboard-game', 'is_active' => true]);
        $this->game->translations()->create(['locale' => 'en', 'name' => 'Dashboard Game', 'description' => 'Dashboard test game.']);
    }

    public function test_completed_tournaments_award_platform_xp_once(): void
    {
        foreach (range(1, 5) as $number) {
            $tournament = $this->tournament("Completed Cup {$number}", TournamentStatus::COMPLETED);
            $tournament->registrations()->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $this->player->id,
                'status' => 'confirmed',
                'payment_status' => 'free',
                'registered_at' => now(),
            ]);
        }

        $service = app(PlayerProgressionService::class);
        $progression = $service->progressionFor($this->player);
        $service->reconcileCompletedTournaments($this->player);
        $progression->refresh();

        $this->assertSame(500, $progression->experience_points);
        $this->assertSame(2, $progression->level);
        $this->assertSame(5, $progression->tournaments_completed);
        $this->assertDatabaseCount('player_experience_awards', 5);
    }

    public function test_dashboard_renders_backend_announcements_chat_and_progression(): void
    {
        $active = $this->tournament('Open Dashboard Cup', TournamentStatus::REGISTRATION_OPEN);
        $active->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->player->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        BroadcastMessage::query()->create([
            'uuid' => Str::uuid()->toString(),
            'title' => 'Maintenance Window',
            'message' => 'Tournament services will be upgraded tonight.',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addHour(),
        ]);

        $conversation = ChatConversation::query()->create([
            'uuid' => Str::uuid()->toString(),
            'type' => ChatConversation::TYPE_GLOBAL,
            'scope_key' => 'global',
            'name' => 'Global Comms',
            'created_by_user_id' => $this->player->id,
        ]);
        ChatMessage::query()->create([
            'uuid' => Str::uuid()->toString(),
            'chat_conversation_id' => $conversation->id,
            'user_id' => $this->player->id,
            'body' => 'Anyone joining the next tournament?',
        ]);

        Livewire::actingAs($this->player)
            ->test(PlayerDashboard::class)
            ->assertSee('Player command center')
            ->assertSee('LEVEL 1')
            ->assertSee('Open Dashboard Cup')
            ->assertSee('Maintenance Window')
            ->assertSee('Anyone joining the next tournament?')
            ->assertSee('Following Online');
    }

    public function test_tournament_completion_listener_is_idempotent(): void
    {
        $tournament = $this->tournament('Listener Cup', TournamentStatus::COMPLETED);
        $tournament->registrations()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $this->player->id,
            'status' => 'confirmed',
            'payment_status' => 'free',
            'registered_at' => now(),
        ]);

        $listener = app(AwardTournamentExperienceListener::class);
        $listener->handle(new TournamentCompleted($tournament->id));
        $listener->handle(new TournamentCompleted($tournament->id));

        $this->assertDatabaseHas('player_progressions', [
            'user_id' => $this->player->id,
            'experience_points' => 100,
            'tournaments_completed' => 1,
        ]);
        $this->assertDatabaseCount('player_experience_awards', 1);
    }

    private function tournament(string $name, TournamentStatus $status): Tournament
    {
        return Tournament::query()->create([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'status' => $status,
            'entry_fee' => 0,
            'prize_pool' => 0,
            'max_participants' => 16,
            'min_participants' => 2,
            'start_at' => now()->addDay(),
            'completed_at' => $status === TournamentStatus::COMPLETED ? now() : null,
            'created_by' => $this->admin->id,
        ]);
    }
}
