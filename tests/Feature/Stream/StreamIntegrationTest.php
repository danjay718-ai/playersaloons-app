<?php

declare(strict_types=1);

namespace Tests\Feature\Stream;

use App\Livewire\Admin\TournamentForm;
use App\Livewire\Stream\StreamList;
use App\Livewire\Stream\StreamWatch;
use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Stream\Models\StreamChatMessage;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use Database\Seeders\GamesTableSeeder;
use Database\Seeders\GameTrailerStreamSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StreamIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $player;

    private Game $game;

    private Platform $platform;

    protected function setUp(): void
    {
        parent::setUp();

        // These admin save/validation cases cover the retained V1 editor.
        // Keep them independent from a developer's V2 feature-flag setting.
        config(['features.tournament_v2.enabled' => false]);
        config(['app.url' => 'https://app-testing.website']);

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = $this->createUserWithRole('ADMIN', 'stream-admin@example.com');
        $this->player = $this->createUserWithRole('PLAYER', 'stream-player@example.com');

        $this->game = Game::query()->create([
            'uuid' => Str::uuid()->toString(),
            'slug' => 'stream-game',
            'is_active' => true,
        ]);

        $this->game->translations()->create([
            'locale' => 'en',
            'name' => 'Stream Game',
            'description' => 'A game with tournament streams.',
        ]);

        $this->platform = Platform::query()->create([
            'name' => 'PC',
            'slug' => 'pc',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_save_supported_stream_urls_on_tournament(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TournamentForm::class)
            ->set('name', 'Broadcast Cup')
            ->set('game_id', $this->game->id)
            ->set('platform_id', $this->platform->id)
            ->set('description', 'A tournament with supported broadcast embeds.')
            ->set('rules', '<ul><li>Follow the rules.</li></ul>')
            ->set('frequency', 'one-time')
            ->set('team_size', 1)
            ->set('waiting_result_time', 10)
            ->set('entry_fee', '0.00')
            ->set('prize_pool', '150.00')
            ->set('min_participants', 4)
            ->set('max_participants', 16)
            ->set('registration_open_at', now()->addMinutes(5)->format('Y-m-d\TH:i'))
            ->set('registration_close_at', now()->addHours(1)->format('Y-m-d\TH:i'))
            ->set('checkin_open_at', now()->addHours(2)->format('Y-m-d\TH:i'))
            ->set('checkin_close_at', now()->addHours(3)->format('Y-m-d\TH:i'))
            ->set('start_at', now()->addHours(4)->format('Y-m-d\TH:i'))
            ->set('youtube_stream_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
            ->set('twitch_stream_url', 'https://www.twitch.tv/player_saloons')
            ->set('facebook_stream_url', 'https://www.facebook.com/player.saloons/videos/123456789')
            ->call('saveTournament')
            ->assertHasNoErrors();

        $tournament = Tournament::query()->where('name', 'Broadcast Cup')->firstOrFail();

        $this->assertDatabaseHas('stream_channels', [
            'tournament_id' => $tournament->id,
            'user_id' => null,
            'provider' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $this->assertDatabaseHas('stream_channels', [
            'tournament_id' => $tournament->id,
            'provider' => 'twitch',
            'source_url' => 'https://www.twitch.tv/player_saloons',
        ]);
        $this->assertDatabaseHas('stream_channels', [
            'tournament_id' => $tournament->id,
            'provider' => 'facebook',
            'source_url' => 'https://www.facebook.com/player.saloons/videos/123456789',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $this->admin->id,
            'subject_type' => Tournament::class,
            'subject_id' => $tournament->id,
            'description' => 'tournament_stream_created',
        ]);
    }

    public function test_admin_stream_url_validation_rejects_wrong_provider_urls(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TournamentForm::class)
            ->set('platform_id', $this->platform->id)
            ->set('frequency', 'one-time')
            ->set('team_size', 1)
            ->set('waiting_result_time', 10)
            ->set('youtube_stream_url', 'https://www.twitch.tv/player_saloons')
            ->call('validateStep', 3)
            ->assertHasErrors(['youtube_stream_url']);
    }

    public function test_streams_page_and_tournament_detail_render_embeds(): void
    {
        $tournament = $this->createStreamedTournament([
            'status' => TournamentStatus::ONGOING,
            'youtube_stream_url' => 'https://youtu.be/dQw4w9WgXcQ',
            'twitch_stream_url' => 'https://www.twitch.tv/player_saloons',
        ]);
        $tournament->streamChannels()->update(['is_live' => true]);

        $this->actingAs($this->player)
            ->get('/streams')
            ->assertOk()
            ->assertSee('Browse Streams')
            // Listing cards link out instead of loading many expensive embeds.
            ->assertSee('https://youtu.be/dQw4w9WgXcQ', false)
            ->assertSee('https://www.twitch.tv/player_saloons', false)
            ->assertSee($tournament->name);

        $this->actingAs($this->player)
            ->get('/tournaments/'.$tournament->uuid.'/view')
            ->assertOk()
            ->assertSee('LIVE BROADCAST')
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false);
    }

    public function test_player_can_publish_stream_and_other_players_can_view_it(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->player)
            ->test(StreamList::class)
            ->set('streamTitle', 'Road to Finals')
            ->set('twitch_stream_url', 'https://www.twitch.tv/player_saloons')
            ->set('streamThumbnail', $this->pngUpload('road-to-finals.png', 960, 540))
            ->set('is_public', true)
            ->call('savePlayerStream')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stream_channels', [
            'user_id' => $this->player->id,
            'title' => 'Road to Finals',
            'provider' => 'twitch',
            'source_url' => 'https://www.twitch.tv/player_saloons',
            'is_public' => true,
            'taken_down_at' => null,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $this->player->id,
            'subject_type' => StreamChannel::class,
            'description' => 'stream_created',
        ]);
        $publishedStream = StreamChannel::query()->where('user_id', $this->player->id)->sole();
        $this->assertStringStartsWith('/storage/streams/thumbnails/'.$this->player->id.'/', (string) $publishedStream->thumbnail_url);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', (string) $publishedStream->thumbnail_url));

        $viewer = $this->createUserWithRole('PLAYER', 'viewer@example.com');

        $this->actingAs($viewer)
            ->get('/streams')
            ->assertOk()
            ->assertSee('Road to Finals')
            ->assertSee($this->player->username)
            ->assertSee('/streams/'.$publishedStream->id, false);

        $this->get('/streams/'.$publishedStream->id)
            ->assertOk()
            ->assertSee('Road to Finals');
    }

    private function pngUpload(string $name, int $width, int $height): UploadedFile
    {
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };
        $row = "\x00".str_repeat("\x00\x00\x00", $width);
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat($row, $height), 9))
            .$chunk('IEND', '');

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    public function test_player_delete_preserves_chat_and_other_streams_and_cancel_closes_the_dialog(): void
    {
        $stream = StreamChannel::query()->create([
            'user_id' => $this->player->id, 'title' => 'Delete My Stream', 'provider' => 'youtube',
            'source_url' => 'https://youtu.be/XN3xNJvWXsc', 'is_public' => true, 'is_live' => true,
        ]);
        $other = StreamChannel::query()->create([
            'user_id' => $this->player->id, 'provider' => 'twitch',
            'source_url' => 'https://www.twitch.tv/player_saloons', 'is_public' => true,
        ]);
        $message = StreamChatMessage::query()->create([
            'stream_channel_id' => $stream->id, 'user_id' => $this->player->id, 'message' => 'Preserve chat',
        ]);
        $component = Livewire::actingAs($this->player)->test(StreamList::class);
        $component->call('confirmDeletePlayerStream', $stream->id)
            ->assertSee('Chat messages, viewer history, and uploaded files are preserved.')
            ->call('cancelDeletePlayerStream')
            ->assertSet('deletePlayerStreamId', null);
        $this->assertFalse($stream->fresh()->trashed());

        $component->call('confirmDeletePlayerStream', $stream->id)
            ->call('deletePlayerStream')
            ->assertSet('deletePlayerStreamId', null)
            ->assertSet('youtube_stream_url', null)
            ->assertSet('twitch_stream_url', $other->source_url);

        $this->assertSoftDeleted($stream);
        $this->assertFalse($stream->fresh()->is_live);
        $this->assertFalse($stream->fresh()->is_public);
        $this->assertNotNull($message->fresh());
        $this->assertFalse($other->fresh()->trashed());
        $this->actingAs($this->player)->get(route('streams.watch', $stream->id))->assertNotFound();
    }

    public function test_player_cannot_delete_someone_elses_or_a_tournament_stream(): void
    {
        $stream = StreamChannel::query()->create([
            'user_id' => $this->admin->id, 'provider' => 'youtube', 'source_url' => 'https://youtu.be/XN3xNJvWXsc',
        ]);
        foreach ([false, true] as $tournamentStream) {
            if ($tournamentStream) {
                $stream->update(['user_id' => $this->player->id, 'tournament_id' => $this->createStreamedTournament()->id]);
            }
            try {
                Livewire::actingAs($this->player)->test(StreamList::class)
                    ->call('confirmDeletePlayerStream', $stream->id);
                $this->fail('Player must not be allowed to select this stream for deletion.');
            } catch (ModelNotFoundException $exception) {
                $this->assertSame(StreamChannel::class, $exception->getModel());
            }
            $this->assertFalse($stream->fresh()->trashed());
        }
    }

    public function test_player_delete_does_not_bypass_an_admin_takedown(): void
    {
        $stream = StreamChannel::query()->create([
            'user_id' => $this->player->id, 'provider' => 'youtube', 'source_url' => 'https://youtu.be/XN3xNJvWXsc',
            'taken_down_at' => now(), 'taken_down_by' => $this->admin->id,
        ]);
        Livewire::actingAs($this->player)->test(StreamList::class)
            ->call('confirmDeletePlayerStream', $stream->id)
            ->call('deletePlayerStream')
            ->set('youtube_stream_url', 'https://youtu.be/XN3xNJvWXsc')
            ->call('savePlayerStream')
            ->assertSee('Your stream is currently taken down by admin review.');
        $this->assertSame(0, StreamChannel::query()->where('user_id', $this->player->id)->count());
    }

    public function test_admin_can_take_down_and_restore_player_streams(): void
    {
        $playerStream = StreamChannel::query()->create([
            'user_id' => $this->player->id,
            'title' => 'Community Scrims',
            'provider' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_public' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test(StreamList::class)
            ->set('takedownReason', 'Invalid stream content')
            ->call('takeDownPlayerStream', $playerStream->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stream_channels', [
            'id' => $playerStream->id,
            'taken_down_by' => $this->admin->id,
            'takedown_reason' => 'Invalid stream content',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $this->admin->id,
            'subject_type' => StreamChannel::class,
            'subject_id' => $playerStream->id,
            'description' => 'stream_taken_down',
        ]);

        $viewer = $this->createUserWithRole('PLAYER', 'viewer-two@example.com');

        $this->actingAs($viewer)
            ->get('/streams')
            ->assertOk()
            ->assertDontSee('Community Scrims');

        Livewire::actingAs($this->admin)
            ->test(StreamList::class)
            ->call('restorePlayerStream', $playerStream->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stream_channels', [
            'id' => $playerStream->id,
            'taken_down_at' => null,
            'taken_down_by' => null,
            'takedown_reason' => null,
        ]);
    }

    public function test_game_trailer_seeder_creates_sample_youtube_trailers(): void
    {
        $this->seed(GamesTableSeeder::class);
        $this->seed(GameTrailerStreamSeeder::class);

        $this->assertSame(5, StreamChannel::query()
            ->whereNotNull('game_id')
            ->where('provider', 'youtube')
            ->count());

        $this->assertDatabaseHas('stream_channels', [
            'provider' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=e_E9W2vsRbQ',
        ]);

        // Game-only channels are seeded as reusable provider metadata. The
        // player hub intentionally lists player and tournament broadcasts.
    }

    public function test_stream_chat_send_ignores_invalid_livewire_socket_id(): void
    {
        Config::set('broadcasting.default', 'reverb');

        $stream = StreamChannel::query()->create([
            'user_id' => $this->player->id,
            'title' => 'Socket Guard Stream',
            'provider' => 'youtube',
            'source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_public' => true,
        ]);

        Livewire::withHeaders(['X-Socket-ID' => 'undefined'])
            ->actingAs($this->player)
            ->test(StreamWatch::class, ['id' => $stream->id])
            ->set('chatMessage', 'Socket should not crash this send.')
            ->call('sendMessage')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stream_chat_messages', [
            'stream_channel_id' => $stream->id,
            'user_id' => $this->player->id,
            'message' => 'Socket should not crash this send.',
        ]);

        $this->assertSame(1, StreamChatMessage::query()->count());
    }

    private function createUserWithRole(string $role, string $email): User
    {
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => $email,
            'username' => Str::before($email, '@'),
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createStreamedTournament(array $overrides = []): Tournament
    {
        $streamUrls = [
            'youtube' => $overrides['youtube_stream_url'] ?? null,
            'twitch' => $overrides['twitch_stream_url'] ?? null,
            'facebook' => $overrides['facebook_stream_url'] ?? null,
        ];

        unset($overrides['youtube_stream_url'], $overrides['twitch_stream_url'], $overrides['facebook_stream_url']);

        $tournament = Tournament::query()->create(array_merge([
            'uuid' => Str::uuid()->toString(),
            'game_id' => $this->game->id,
            'name' => 'Live Stream Cup',
            'slug' => 'live-stream-cup',
            'status' => TournamentStatus::ONGOING,
            'entry_fee' => '0.00',
            'prize_pool' => '100.00',
            'max_participants' => 16,
            'min_participants' => 4,
            'created_by' => $this->admin->id,
            'registration_open_at' => now()->subDays(2),
            'registration_close_at' => now()->subDay(),
            'checkin_open_at' => now()->subHours(3),
            'checkin_close_at' => now()->subHours(2),
            'start_at' => now()->subHour(),
        ], $overrides));

        foreach ($streamUrls as $provider => $url) {
            if (is_string($url)) {
                StreamChannel::query()->create([
                    'tournament_id' => $tournament->id,
                    'provider' => $provider,
                    'source_url' => $url,
                    'title' => $tournament->name,
                    'is_public' => true,
                ]);
            }
        }

        return $tournament;
    }
}
