<?php

declare(strict_types=1);

namespace App\Modules\Stream\Support;

use App\Modules\Stream\Models\StreamChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProviderLiveStatusService
{
    public function refresh(StreamChannel $channel): ?bool
    {
        try {
            $isLive = match ($channel->provider) {
                'youtube' => $this->youtube($channel->source_url), 'twitch' => $this->twitch($channel->source_url), 'facebook' => $this->facebook($channel->source_url), default => null
            };
            if ($isLive === null) {
                $channel->forceFill(['provider_status' => 'unavailable', 'provider_checked_at' => now(), 'provider_status_error' => null])->save();

                return null;
            }
            $channel->forceFill(['is_live' => $isLive, 'provider_status' => $isLive ? 'live' : 'offline', 'provider_checked_at' => now(), 'provider_status_error' => null])->save();

            return $isLive;
        } catch (Throwable $exception) {
            $channel->forceFill(['provider_status' => 'error', 'provider_checked_at' => now(), 'provider_status_error' => Str::limit($exception->getMessage(), 1000, '')])->save();

            return null;
        }
    }

    private function youtube(string $url): ?bool
    {
        $key = config('services.youtube.api_key');
        if (! is_string($key) || $key === '') {
            return null;
        }
        $videoId = $this->youtubeVideoId($url) ?? throw new RuntimeException('Unable to determine YouTube video ID.');
        $item = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/videos', ['part' => 'snippet,liveStreamingDetails', 'id' => $videoId, 'key' => $key])->throw()->json('items.0');

        return is_array($item) && data_get($item, 'snippet.liveBroadcastContent') === 'live';
    }

    private function twitch(string $url): ?bool
    {
        $clientId = config('services.twitch.client_id');
        $token = config('services.twitch.access_token');
        if (! is_string($clientId) || $clientId === '' || ! is_string($token) || $token === '') {
            return null;
        }
        $channel = explode('/', trim((string) parse_url($url, PHP_URL_PATH), '/'))[0] ?? '';
        if ($channel === '') {
            throw new RuntimeException('Unable to determine Twitch channel.');
        }
        $data = Http::timeout(8)->acceptJson()->withHeaders(['Client-Id' => $clientId, 'Authorization' => 'Bearer '.$token])->get('https://api.twitch.tv/helix/streams', ['user_login' => $channel])->throw()->json('data');

        return is_array($data) && $data !== [];
    }

    private function facebook(string $url): ?bool
    {
        $token = config('services.facebook.access_token');
        if (! is_string($token) || $token === '') {
            return null;
        }
        if (! preg_match('~/(?:videos/|live/)?(\d{6,})~', (string) parse_url($url, PHP_URL_PATH), $matches)) {
            throw new RuntimeException('Unable to determine Facebook video ID.');
        }
        $status = Http::timeout(8)->get('https://graph.facebook.com/v21.0/'.$matches[1], ['fields' => 'live_status', 'access_token' => $token])->throw()->json('live_status');

        return $status === 'LIVE';
    }

    private function youtubeVideoId(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (str_ends_with($host, 'youtu.be')) {
            return explode('/', $path)[0] ?: null;
        }
        if (isset($query['v']) && is_string($query['v'])) {
            return $query['v'];
        }

        return str_starts_with($path, 'live/') ? Str::after($path, 'live/') : null;
    }
}
