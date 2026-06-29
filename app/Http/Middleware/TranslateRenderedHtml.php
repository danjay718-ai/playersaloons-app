<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TranslateRenderedHtml
{
    /** @var array<int, string> */
    private array $protectedBlocks = [];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTranslateHtml($response)) {
            $response->setContent($this->translateHtml((string) $response->getContent()));

            return $response;
        }

        if ($this->shouldTranslateLivewireJson($request, $response)) {
            $payload = json_decode((string) $response->getContent(), true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $response->setContent((string) json_encode($this->translateLivewirePayload($payload)));
            }

            return $response;
        }

        return $response;
    }

    private function translateHtml(string $content): string
    {
        $content = $this->protectBlocks($content);
        $content = $this->translateAttributes($content);
        $content = $this->translateTextNodes($content);

        return $this->restoreBlocks($content);
    }

    private function shouldTranslateHtml(Response $response): bool
    {
        if (! $response->isSuccessful()) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        return $contentType === '' || str_contains($contentType, 'text/html');
    }

    private function shouldTranslateLivewireJson(Request $request, Response $response): bool
    {
        if (! $response->isSuccessful() || ! $request->is('livewire/*')) {
            return false;
        }

        return str_contains(strtolower((string) $response->headers->get('Content-Type')), 'application/json');
    }

    /**
     * @param mixed $payload
     * @return mixed
     */
    private function translateLivewirePayload(mixed $payload): mixed
    {
        if (is_array($payload)) {
            foreach ($payload as $key => $value) {
                $payload[$key] = $this->translateLivewirePayload($value);
            }

            return $payload;
        }

        if (is_string($payload) && str_contains($payload, '<')) {
            return $this->translateHtml($payload);
        }

        return $payload;
    }

    private function protectBlocks(string $content): string
    {
        $this->protectedBlocks = [];

        return (string) preg_replace_callback(
            '#<(script|style|pre|code|textarea)\b[^>]*>.*?</\1>#is',
            function (array $matches): string {
                $token = '___PS_I18N_BLOCK_'.count($this->protectedBlocks).'___';
                $this->protectedBlocks[$token] = $matches[0];

                return $token;
            },
            $content
        );
    }

    private function restoreBlocks(string $content): string
    {
        return strtr($content, $this->protectedBlocks);
    }

    private function translateAttributes(string $content): string
    {
        return (string) preg_replace_callback(
            '/\b(placeholder|aria-label|title|alt)="([^"]*[[:alpha:]][^"]*)"/u',
            function (array $matches): string {
                return $matches[1].'="'.e(__($matches[2]), false).'"';
            },
            $content
        );
    }

    private function translateTextNodes(string $content): string
    {
        return (string) preg_replace_callback(
            '/>([^<]*[[:alpha:]][^<]*)</u',
            function (array $matches): string {
                $text = $matches[1];
                preg_match('/^\s*/u', $text, $leadingMatch);
                preg_match('/\s*$/u', $text, $trailingMatch);
                $leading = $leadingMatch[0] ?? '';
                $trailing = $trailingMatch[0] ?? '';
                $key = trim($text);

                if ($key === '') {
                    return $matches[0];
                }

                return '>'.$leading.e(__($key), false).$trailing.'<';
            },
            $content
        );
    }
}
