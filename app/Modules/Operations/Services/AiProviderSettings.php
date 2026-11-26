<?php

declare(strict_types=1);

namespace App\Modules\Operations\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AiProviderSettings
{
    public const PROVIDER_GEMINI = 'gemini';

    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_ANTHROPIC = 'anthropic';

    public const PROVIDER_OPENROUTER = 'openrouter';

    /** @var list<string> */
    public const PROVIDERS = [
        self::PROVIDER_GEMINI,
        self::PROVIDER_OPENAI,
        self::PROVIDER_ANTHROPIC,
        self::PROVIDER_OPENROUTER,
    ];

    public function __construct(private readonly SystemSettings $settings) {}

    /**
     * Catalog of supported AI providers for platform-wide AI features.
     *
     * @return array<string, array{name: string, description: string, default_model: string, default_base_url: string, env_key: string, recommended_models: list<string>}>
     */
    public function catalog(): array
    {
        return [
            self::PROVIDER_GEMINI => [
                'name' => 'Google Gemini',
                'description' => 'Multimodal vision & fast structured JSON',
                'default_model' => 'gemini-2.5-flash',
                'default_base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'env_key' => 'GEMINI_API_KEY',
                'recommended_models' => ['gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash'],
            ],
            self::PROVIDER_OPENAI => [
                'name' => 'OpenAI',
                'description' => 'GPT-4.1 & GPT-4o vision models',
                'default_model' => 'gpt-4.1-mini',
                'default_base_url' => 'https://api.openai.com/v1',
                'env_key' => 'OPENAI_API_KEY',
                'recommended_models' => ['gpt-4.1-mini', 'gpt-4.1', 'gpt-4o-mini', 'gpt-4o'],
            ],
            self::PROVIDER_ANTHROPIC => [
                'name' => 'Anthropic Claude',
                'description' => 'Claude Sonnet & Haiku vision models',
                'default_model' => 'claude-sonnet-4-5',
                'default_base_url' => 'https://api.anthropic.com/v1',
                'env_key' => 'ANTHROPIC_API_KEY',
                'recommended_models' => ['claude-sonnet-4-5', 'claude-3-7-sonnet-latest', 'claude-3-5-haiku-latest'],
            ],
            self::PROVIDER_OPENROUTER => [
                'name' => 'OpenRouter',
                'description' => 'Multi-provider OpenAI-compatible router',
                'default_model' => 'google/gemini-2.5-flash',
                'default_base_url' => 'https://openrouter.ai/api/v1',
                'env_key' => 'OPENROUTER_API_KEY',
                'recommended_models' => ['google/gemini-2.5-flash', 'openai/gpt-4.1-mini', 'anthropic/claude-sonnet-4.5'],
            ],
        ];
    }

    public function activeProvider(): string
    {
        $provider = strtolower(trim((string) $this->settings->get('ai.provider', self::PROVIDER_GEMINI)));

        return in_array($provider, self::PROVIDERS, true) ? $provider : self::PROVIDER_GEMINI;
    }

    public function credentialSettingKey(string $provider): string
    {
        return "ai.credentials.{$provider}.api_key";
    }

    public function modelSettingKey(string $provider): string
    {
        return "ai.providers.{$provider}.model";
    }

    public function baseUrlSettingKey(string $provider): string
    {
        return "ai.providers.{$provider}.base_url";
    }

    public function apiKey(?string $provider = null): string
    {
        $provider = $this->normalizeProvider($provider);
        $rawOverride = $this->settings->get($this->credentialSettingKey($provider));

        if (is_string($rawOverride) && $rawOverride !== '') {
            try {
                return trim(Crypt::decryptString($rawOverride));
            } catch (DecryptException) {
                return '';
            }
        }

        return trim((string) config("services.{$provider}.api_key", ''));
    }

    public function source(?string $provider = null): string
    {
        $provider = $this->normalizeProvider($provider);
        $rawOverride = $this->settings->get($this->credentialSettingKey($provider));

        return is_string($rawOverride) && $rawOverride !== '' ? 'database' : 'environment';
    }

    public function isConfigured(?string $provider = null): bool
    {
        return $this->apiKey($provider) !== '';
    }

    public function model(?string $provider = null): string
    {
        $provider = $this->normalizeProvider($provider);
        $saved = trim((string) $this->settings->get($this->modelSettingKey($provider), ''));
        if ($saved !== '') {
            return $saved;
        }

        if ($provider === self::PROVIDER_GEMINI) {
            $envModel = trim((string) config('services.gemini.dispute_model', ''));
            if ($envModel !== '') {
                return $envModel;
            }
        }

        $envDefault = trim((string) config("services.{$provider}.model", ''));
        if ($envDefault !== '') {
            return $envDefault;
        }

        return $this->catalog()[$provider]['default_model'];
    }

    public function baseUrl(?string $provider = null): string
    {
        $provider = $this->normalizeProvider($provider);
        $saved = trim((string) $this->settings->get($this->baseUrlSettingKey($provider), ''));
        if ($saved !== '') {
            return rtrim($saved, '/');
        }

        $envUrl = trim((string) config("services.{$provider}.base_url", ''));
        if ($envUrl !== '') {
            return rtrim($envUrl, '/');
        }

        return $this->catalog()[$provider]['default_base_url'];
    }

    public function savedBaseUrlOverride(string $provider): string
    {
        $provider = $this->normalizeProvider($provider);

        return trim((string) $this->settings->get($this->baseUrlSettingKey($provider), ''));
    }

    public function timeoutSeconds(?string $provider = null): int
    {
        $provider = $this->normalizeProvider($provider);

        return max(5, (int) config("services.{$provider}.timeout_seconds", 30));
    }

    /**
     * Lightweight zero-token connectivity check against the selected AI provider.
     *
     * @return array{ok: bool, message: string, status: int|null}
     */
    public function testConnection(?string $provider = null): array
    {
        $provider = $this->normalizeProvider($provider);
        $providerName = $this->catalog()[$provider]['name'];
        $apiKey = $this->apiKey($provider);

        if ($apiKey === '') {
            return [
                'ok' => false,
                'status' => null,
                'message' => __(':provider API key is not configured.', ['provider' => $providerName]),
            ];
        }

        $baseUrl = $this->baseUrl($provider);
        $model = $this->model($provider);

        try {
            $request = Http::acceptJson()->connectTimeout(5)->timeout(10);

            $response = match ($provider) {
                self::PROVIDER_GEMINI => $request
                    ->withHeaders(['x-goog-api-key' => $apiKey])
                    ->get("{$baseUrl}/models/".rawurlencode($model)),
                self::PROVIDER_ANTHROPIC => $request
                    ->withHeaders([
                        'x-api-key' => $apiKey,
                        'anthropic-version' => '2023-06-01',
                    ])
                    ->get("{$baseUrl}/models"),
                default => $request
                    ->withToken($apiKey)
                    ->get("{$baseUrl}/models"),
            };

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'status' => $response->status(),
                    'message' => __(':provider connection verified (model: :model).', [
                        'provider' => $providerName,
                        'model' => $model,
                    ]),
                ];
            }

            return [
                'ok' => false,
                'status' => $response->status(),
                'message' => __(':provider returned HTTP :status. Check the API key, model name, or base URL.', [
                    'provider' => $providerName,
                    'status' => $response->status(),
                ]),
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'status' => null,
                'message' => __(':provider connection failed: :error', [
                    'provider' => $providerName,
                    'error' => mb_substr($exception->getMessage(), 0, 180),
                ]),
            ];
        }
    }

    private function normalizeProvider(?string $provider): string
    {
        if ($provider === null || trim($provider) === '') {
            return $this->activeProvider();
        }

        $normalized = strtolower(trim($provider));

        return in_array($normalized, self::PROVIDERS, true) ? $normalized : self::PROVIDER_GEMINI;
    }
}
