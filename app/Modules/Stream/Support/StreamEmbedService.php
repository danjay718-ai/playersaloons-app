<?php

declare(strict_types=1);

namespace App\Modules\Stream\Support;

use App\Modules\Stream\Models\StreamChannel;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class StreamEmbedService
{
    /**
     * @return list<array{provider: string, label: string, url: string, embed_url: string, icon: string}>
     */
    public function streamsForTournament(Tournament $tournament): array
    {
        return $this->streamsForChannels($tournament->streamChannels);
    }

    /**
     * @return list<array{provider: string, label: string, url: string, embed_url: string, icon: string}>
     */
    public function streamForChannel(StreamChannel $streamChannel): ?array
    {
        $embedUrl = $this->embedUrl($streamChannel->provider, $streamChannel->source_url);

        if ($embedUrl === null) {
            return null;
        }

        $config = $this->providerConfig()[$streamChannel->provider] ?? null;

        return [
            'provider' => $streamChannel->provider,
            'label' => (string) ($config['label'] ?? Str::headline($streamChannel->provider)),
            'url' => $streamChannel->source_url,
            'embed_url' => $embedUrl,
            'icon' => (string) ($config['icon'] ?? 'radio'),
        ];
    }

    /**
     * @param  iterable<StreamChannel>  $streamChannels
     * @return list<array{provider: string, label: string, url: string, embed_url: string, icon: string}>
     */
    public function streamsForChannels(iterable $streamChannels): array
    {
        $streams = [];

        foreach ($streamChannels as $streamChannel) {
            if (! $streamChannel->is_live) {
                continue;
            }
            $stream = $this->streamForChannel($streamChannel);

            if ($stream !== null) {
                $streams[] = $stream;
            }
        }

        return $streams;
    }

    public function isValidProviderUrl(string $provider, ?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            return true;
        }

        return $this->embedUrl($provider, $url) !== null;
    }

    /**
     * @return array{label: string, class: string}
     */
    public function statusLabel(Tournament $tournament): array
    {
        $status = $tournament->status;

        return match ($status) {
            TournamentStatus::ONGOING,
            TournamentStatus::BRACKET_GENERATED => [
                'label' => 'Live',
                'class' => 'text-rose-300 border-rose-500/50 bg-rose-500/10',
            ],
            TournamentStatus::COMPLETED => [
                'label' => 'Replay',
                'class' => 'text-zinc-300 border-zinc-600 bg-zinc-800/70',
            ],
            default => [
                'label' => 'Scheduled',
                'class' => 'text-cyan-300 border-cyan-500/40 bg-cyan-500/10',
            ],
        };
    }

    public function embedUrl(string $provider, string $url): ?string
    {
        return match ($provider) {
            'youtube' => $this->youtubeEmbedUrl($url),
            'twitch' => $this->twitchEmbedUrl($url),
            'facebook' => $this->facebookEmbedUrl($url),
            default => null,
        };
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    private function providerConfig(): array
    {
        return [
            'youtube' => ['label' => 'YouTube', 'icon' => 'play'],
            'twitch' => ['label' => 'Twitch', 'icon' => 'tv'],
            'facebook' => ['label' => 'Facebook', 'icon' => 'video'],
        ];
    }

    private function youtubeEmbedUrl(string $url): ?string
    {
        $parts = $this->parseHttpsUrl($url);

        if ($parts === null) {
            return null;
        }

        $host = Str::lower((string) Arr::get($parts, 'host', ''));
        $path = trim((string) Arr::get($parts, 'path', ''), '/');
        parse_str((string) Arr::get($parts, 'query', ''), $query);

        $videoId = null;

        if (Str::endsWith($host, 'youtu.be')) {
            $videoId = Str::before($path, '/');
        } elseif (Str::contains($host, 'youtube.com')) {
            if (isset($query['v']) && is_string($query['v'])) {
                $videoId = $query['v'];
            } elseif (Str::startsWith($path, 'live/')) {
                $videoId = Str::after($path, 'live/');
            } elseif (Str::startsWith($path, 'embed/')) {
                $videoId = Str::after($path, 'embed/');
            }
        }

        if (! is_string($videoId) || ! preg_match('/^[A-Za-z0-9_-]{6,}$/', $videoId)) {
            return null;
        }

        return 'https://www.youtube.com/embed/'.rawurlencode($videoId);
    }

    private function twitchEmbedUrl(string $url): ?string
    {
        $parts = $this->parseHttpsUrl($url);

        if ($parts === null) {
            return null;
        }

        $host = Str::lower((string) Arr::get($parts, 'host', ''));
        $path = trim((string) Arr::get($parts, 'path', ''), '/');

        if (! Str::contains($host, 'twitch.tv') || $path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path)));
        $parent = rawurlencode($this->embedParentHost());

        if (($segments[0] ?? null) === 'videos' && isset($segments[1]) && preg_match('/^\d+$/', $segments[1])) {
            return 'https://player.twitch.tv/?video='.rawurlencode($segments[1]).'&parent='.$parent;
        }

        $channel = $segments[0] ?? null;

        if (! is_string($channel) || ! preg_match('/^[A-Za-z0-9_]{3,25}$/', $channel)) {
            return null;
        }

        return 'https://player.twitch.tv/?channel='.rawurlencode($channel).'&parent='.$parent;
    }

    private function facebookEmbedUrl(string $url): ?string
    {
        $parts = $this->parseHttpsUrl($url);

        if ($parts === null) {
            return null;
        }

        $host = Str::lower((string) Arr::get($parts, 'host', ''));

        if (! Str::contains($host, 'facebook.com') && ! Str::endsWith($host, 'fb.watch')) {
            return null;
        }

        return 'https://www.facebook.com/plugins/video.php?href='.rawurlencode($url).'&show_text=false&width=1280';
    }

    /**
     * @return array<string, int|string>|null
     */
    private function parseHttpsUrl(string $url): ?array
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || Arr::get($parts, 'scheme') !== 'https' || ! isset($parts['host'])) {
            return null;
        }

        return $parts;
    }

    private function embedParentHost(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return $host;
        }

        return request()->getHost();
    }
}
