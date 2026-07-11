<?php

declare(strict_types=1);

namespace Tests\Feature\Stream;

use App\Modules\Identity\Models\User;
use App\Modules\Stream\Jobs\RefreshProviderLiveStatusesJob;
use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Stream\Support\ProviderLiveStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderLiveStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_live_response_updates_channel(): void
    {
        config(['services.youtube.api_key' => 'test-key']);
        Http::fake([
            'www.googleapis.com/youtube/v3/videos*' => Http::response([
                'items' => [['snippet' => ['liveBroadcastContent' => 'live'], 'liveStreamingDetails' => []]],
            ]),
        ]);
        $channel = $this->channel('youtube', 'https://youtube.com/watch?v=abcdef12345');

        $this->assertTrue(app(ProviderLiveStatusService::class)->refresh($channel));
        $this->assertTrue($channel->fresh()->is_live);
        $this->assertSame('live', $channel->fresh()->provider_status);
    }

    public function test_twitch_offline_response_updates_channel(): void
    {
        config(['services.twitch.client_id' => 'client', 'services.twitch.access_token' => 'token']);
        Http::fake(['api.twitch.tv/helix/streams*' => Http::response(['data' => []])]);
        $channel = $this->channel('twitch', 'https://twitch.tv/testchannel', true);

        $this->assertFalse(app(ProviderLiveStatusService::class)->refresh($channel));
        $this->assertFalse($channel->fresh()->is_live);
        $this->assertSame('offline', $channel->fresh()->provider_status);
    }

    public function test_missing_credentials_preserve_manual_status(): void
    {
        config(['services.youtube.api_key' => null]);
        $channel = $this->channel('youtube', 'https://youtu.be/abcdef12345', true);

        $this->assertNull(app(ProviderLiveStatusService::class)->refresh($channel));
        $this->assertTrue($channel->fresh()->is_live);
        $this->assertSame('unavailable', $channel->fresh()->provider_status);
    }

    public function test_provider_errors_are_recorded_without_clearing_status(): void
    {
        config(['services.youtube.api_key' => 'test-key']);
        Http::fake(['www.googleapis.com/*' => Http::response([], 503)]);
        $channel = $this->channel('youtube', 'https://youtu.be/abcdef12345', true);

        $this->assertNull(app(ProviderLiveStatusService::class)->refresh($channel));
        $this->assertTrue($channel->fresh()->is_live);
        $this->assertSame('error', $channel->fresh()->provider_status);
        $this->assertNotNull($channel->fresh()->provider_status_error);
    }

    public function test_refresh_job_skips_private_and_taken_down_channels(): void
    {
        config(['services.youtube.api_key' => 'test-key']);
        Http::fake(['www.googleapis.com/youtube/v3/videos*' => Http::response(['items' => [['snippet' => ['liveBroadcastContent' => 'live']]]])]);
        $public = $this->channel('youtube', 'https://youtu.be/abcdef12345');
        $private = $this->channel('youtube', 'https://youtu.be/bcdefg12345');
        $private->update(['is_public' => false]);
        $takenDown = $this->channel('youtube', 'https://youtu.be/cdefgh12345');
        $takenDown->update(['taken_down_at' => now()]);

        app(RefreshProviderLiveStatusesJob::class)->handle(app(ProviderLiveStatusService::class));

        $this->assertSame('live', $public->fresh()->provider_status);
        $this->assertNull($private->fresh()->provider_checked_at);
        $this->assertNull($takenDown->fresh()->provider_checked_at);
    }

    private function channel(string $provider, string $url, bool $isLive = false): StreamChannel
    {
        $user = User::factory()->create();

        return StreamChannel::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'source_url' => $url,
            'title' => 'Test Stream',
            'is_public' => true,
            'is_live' => $isLive,
        ]);
    }
}
