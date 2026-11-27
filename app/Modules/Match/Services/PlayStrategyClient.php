<?php

declare(strict_types=1);

namespace App\Modules\Match\Services;

use App\Modules\Match\Exceptions\PlayStrategyException;
use App\Modules\Match\Exceptions\PlayStrategyRateLimitException;
use App\Modules\Match\Exceptions\PlayStrategyUnavailableException;
use App\Modules\Match\Exceptions\PlayStrategyUncertainOutcomeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PlayStrategyClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.playstrategy.base_url', 'https://playstrategy.org'), '/');
    }

    public function buildPersonalTokenUrl(string $description = 'GamersRival'): string
    {
        $scopes = array_values(array_filter(
            preg_split('/\s+/', trim((string) config('services.playstrategy.scopes', 'challenge:write'))) ?: ['challenge:write'],
        ));

        $params = ['description='.rawurlencode($description)];
        foreach ($scopes as $scope) {
            $params[] = 'scopes[]='.rawurlencode($scope);
        }

        return $this->baseUrl().'/account/oauth/token/create?'.implode('&', $params);
    }

    public function buildAuthorizeUrl(string $redirectUri, string $codeChallenge, string $state): string
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => (string) config('services.playstrategy.client_id', 'gamersrival.com'),
            'redirect_uri' => $redirectUri,
            'code_challenge_method' => 'S256',
            'code_challenge' => $codeChallenge,
            'scope' => (string) config('services.playstrategy.scopes', 'challenge:write'),
            'state' => $state,
        ]);

        return $this->baseUrl().'/oauth?'.$query;
    }

    /**
     * Exchange an OAuth2 PKCE authorization code for an access token.
     *
     * @return array{access_token: string, refresh_token?: string, token_type?: string, expires_in?: int, scope?: string}
     */
    public function exchangeAuthorizationCode(string $code, string $codeVerifier, string $redirectUri): array
    {
        try {
            $response = $this->http()->asForm()->post('/api/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'redirect_uri' => $redirectUri,
                'client_id' => (string) config('services.playstrategy.client_id', 'gamersrival.com'),
            ]);
        } catch (ConnectionException $e) {
            throw new PlayStrategyUnavailableException('Unable to reach PlayStrategy OAuth token endpoint.', 0, $e);
        }

        $this->throwIfErrored($response, false);
        $data = $response->json();

        if (! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new PlayStrategyException('PlayStrategy did not return a valid OAuth access token.');
        }

        return $data;
    }

    /**
     * Retrieve the authenticated PlayStrategy account profile.
     *
     * @return array{id: string, username: string}
     */
    public function fetchAccount(string $accessToken): array
    {
        try {
            $response = $this->http()->withToken($accessToken)->acceptJson()->get('/api/account');
        } catch (ConnectionException $e) {
            throw new PlayStrategyUnavailableException('Unable to verify PlayStrategy account.', 0, $e);
        }

        $this->throwIfErrored($response, false);
        $data = $response->json();

        $id = is_array($data) ? trim((string) ($data['id'] ?? '')) : '';
        $username = is_array($data) ? trim((string) ($data['username'] ?? $id)) : '';

        if ($id === '' || $username === '') {
            throw new PlayStrategyException('PlayStrategy account verification returned incomplete identity data.');
        }

        return [
            'id' => strtolower($id),
            'username' => $username,
        ];
    }

    /**
     * Create and immediately accept a PlayStrategy game between two OAuth-linked players.
     *
     * @param  array{variant: string, clock_limit: int, clock_increment: int, rated: bool}  $settings
     * @return array{id: string, url: string, payload: array<string, mixed>}
     */
    public function createChallengeGame(
        string $playerAToken,
        string $playerBUsername,
        string $playerBToken,
        array $settings,
        string $message = 'Your GamersRival tournament match is ready: {game}',
    ): array {
        $variantKey = match (strtolower(trim($settings['variant']))) {
            'chess' => 'standard',
            'draughts' => 'international',
            default => trim($settings['variant']),
        };

        try {
            $response = $this->http()
                ->withToken($playerAToken)
                ->acceptJson()
                ->asForm()
                ->post('/api/challenge/'.rawurlencode($playerBUsername), [
                    'rated' => $settings['rated'] ? 'true' : 'false',
                    'clock.limit' => $settings['clock_limit'],
                    'clock.increment' => $settings['clock_increment'],
                    'clock.byoyomi' => 0,
                    'clock.periods' => 1,
                    'clock.delay' => 0,
                    'playerIndex' => 'p1',
                    'variant' => $variantKey,
                    'acceptByToken' => $playerBToken,
                    'message' => $message,
                ]);
        } catch (ConnectionException $e) {
            // A timeout during POST may have already created the game on PlayStrategy.
            // Callers MUST reconcile before creating another game.
            throw new PlayStrategyUncertainOutcomeException('PlayStrategy game creation timed out with an uncertain outcome.', 0, $e);
        }

        if ($response->status() === 504 || $response->status() === 502) {
            throw new PlayStrategyUncertainOutcomeException('PlayStrategy gateway timed out during game creation; reconciliation required.', $response->status());
        }

        $this->throwIfErrored($response, false);
        $data = $response->json();

        if (! is_array($data)) {
            throw new PlayStrategyUncertainOutcomeException('PlayStrategy returned a non-JSON response during game creation.');
        }

        $gameNode = is_array($data['game'] ?? null) ? $data['game'] : (is_array($data['challenge'] ?? null) ? $data['challenge'] : $data);
        $gameId = trim((string) ($gameNode['id'] ?? $data['id'] ?? ''));

        if ($gameId === '') {
            throw new PlayStrategyUncertainOutcomeException('PlayStrategy response did not include a game ID; reconciliation required.');
        }

        $url = trim((string) ($gameNode['url'] ?? $data['url'] ?? ($this->baseUrl().'/'.$gameId)));

        return [
            'id' => $gameId,
            'url' => $url,
            'payload' => $data,
        ];
    }

    /**
     * Fetch a single PlayStrategy game payload in JSON format.
     *
     * @return array<string, mixed>
     */
    public function fetchGame(string $externalGameId, ?string $bearerToken = null): array
    {
        try {
            $request = $this->http()->acceptJson();
            if ($bearerToken !== null && $bearerToken !== '') {
                $request = $request->withToken($bearerToken);
            }

            $response = $request->get('/game/export/'.rawurlencode($externalGameId), [
                'moves' => 'false',
                'evals' => 'false',
                'opening' => 'false',
            ]);
        } catch (ConnectionException $e) {
            throw new PlayStrategyUnavailableException('Unable to reach PlayStrategy game export endpoint.', 0, $e);
        }

        $this->throwIfErrored($response, false);
        $data = $response->json();

        if (! is_array($data) || trim((string) ($data['id'] ?? '')) === '') {
            throw new PlayStrategyUnavailableException('PlayStrategy returned empty or invalid game data.');
        }

        return $data;
    }

    /**
     * Search recent games between two verified PlayStrategy usernames for reconciliation.
     *
     * @return list<array<string, mixed>>
     */
    public function findRecentGamesBetween(
        string $username,
        string $opponentUsername,
        int $sinceMs,
        ?string $bearerToken = null,
    ): array {
        try {
            $request = $this->http()->withHeaders(['Accept' => 'application/x-ndjson']);
            if ($bearerToken !== null && $bearerToken !== '') {
                $request = $request->withToken($bearerToken);
            }

            $response = $request->get('/api/games/user/'.rawurlencode($username), [
                'vs' => $opponentUsername,
                'since' => $sinceMs,
                'max' => 10,
                'moves' => 'false',
                'ongoing' => 'true',
            ]);
        } catch (ConnectionException $e) {
            throw new PlayStrategyUnavailableException('Unable to reach PlayStrategy games list for reconciliation.', 0, $e);
        }

        $this->throwIfErrored($response, false);

        $body = trim($response->body());
        if ($body === '') {
            return [];
        }

        // Support either NDJSON (PlayStrategy default) or JSON array/object responses.
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (array_is_list($decoded)) {
                return array_values(array_filter($decoded, 'is_array'));
            }

            return [$decoded];
        }

        $games = [];
        foreach (preg_split('/\R+/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $item = json_decode($line, true);
            if (is_array($item)) {
                $games[] = $item;
            }
        }

        return $games;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->timeout(max(2, (int) config('services.playstrategy.timeout_seconds', 10)))
            ->connectTimeout(5);
    }

    private function throwIfErrored(Response $response, bool $treatServerErrorAsUncertain): void
    {
        if ($response->successful()) {
            return;
        }

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 60);

            throw new PlayStrategyRateLimitException(max(10, $retryAfter));
        }

        if ($response->serverError()) {
            if ($treatServerErrorAsUncertain) {
                throw new PlayStrategyUncertainOutcomeException('PlayStrategy server error with uncertain outcome.', $response->status());
            }

            throw new PlayStrategyUnavailableException('PlayStrategy API is temporarily unavailable (HTTP '.$response->status().').', $response->status());
        }

        $rawError = $response->json('error') ?? $response->json('message') ?? $response->json();
        if (is_array($rawError)) {
            $errorMessage = (string) json_encode($rawError, JSON_UNESCAPED_SLASHES);
        } elseif (is_scalar($rawError) && (string) $rawError !== '') {
            $errorMessage = (string) $rawError;
        } else {
            $errorMessage = 'HTTP '.$response->status();
        }

        throw new PlayStrategyException('PlayStrategy request rejected: '.$errorMessage, $response->status());
    }
}
