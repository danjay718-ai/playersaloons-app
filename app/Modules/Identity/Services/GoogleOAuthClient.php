<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Exceptions\GoogleOAuthException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GoogleOAuthClient
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function isConfigured(): bool
    {
        return trim((string) config('services.google.client_id', '')) !== ''
            && trim((string) config('services.google.client_secret', '')) !== '';
    }

    public function buildAuthorizeUrl(string $redirectUri, string $codeChallenge, string $state): string
    {
        $query = http_build_query([
            'client_id' => trim((string) config('services.google.client_id', '')),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
            'access_type' => 'online',
        ]);

        return self::AUTHORIZE_URL.'?'.$query;
    }

    /**
     * Exchange a Google OAuth 2.0 PKCE authorization code for tokens.
     *
     * @return array{access_token: string, refresh_token?: string, expires_in?: int, scope?: string, token_type?: string}
     */
    public function exchangeAuthorizationCode(string $code, string $codeVerifier, string $redirectUri): array
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->asForm()
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'code_verifier' => $codeVerifier,
                    'redirect_uri' => $redirectUri,
                    'client_id' => trim((string) config('services.google.client_id', '')),
                    'client_secret' => trim((string) config('services.google.client_secret', '')),
                ]);
        } catch (ConnectionException $e) {
            throw new GoogleOAuthException(__('Unable to reach Google authentication servers. Please try again.'), 0, $e);
        }

        if (! $response->successful()) {
            $error = (string) ($response->json('error_description') ?? $response->json('error') ?? ('HTTP '.$response->status()));
            throw new GoogleOAuthException(__('Google authentication failed: :error', ['error' => $error]), $response->status());
        }

        $data = $response->json();
        if (! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new GoogleOAuthException(__('Google did not return a valid access token.'));
        }

        return $data;
    }

    /**
     * Fetch the verified Google OIDC user profile.
     *
     * @return array{sub: string, email: string, email_verified: bool, name: string, picture: string|null}
     */
    public function fetchUserInfo(string $accessToken): array
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->withToken($accessToken)
                ->get(self::USERINFO_URL);
        } catch (ConnectionException $e) {
            throw new GoogleOAuthException(__('Unable to verify your Google account right now. Please try again.'), 0, $e);
        }

        if (! $response->successful()) {
            throw new GoogleOAuthException(__('Unable to retrieve your Google profile (HTTP :status).', ['status' => $response->status()]), $response->status());
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new GoogleOAuthException(__('Google returned an invalid profile response.'));
        }

        $sub = trim((string) ($data['sub'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $emailVerified = filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $name = trim((string) ($data['name'] ?? ''));
        $picture = is_string($data['picture'] ?? null) && trim($data['picture']) !== '' ? trim($data['picture']) : null;

        if ($sub === '' || $email === '') {
            throw new GoogleOAuthException(__('Google profile did not include an account ID and email address.'));
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'email_verified' => $emailVerified,
            'name' => $name,
            'picture' => $picture,
        ];
    }
}
