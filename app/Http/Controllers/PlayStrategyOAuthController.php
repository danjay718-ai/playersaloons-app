<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Models\ExternalProviderAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Exceptions\PlayStrategyException;
use App\Modules\Match\Services\PlayStrategyClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlayStrategyOAuthController extends Controller
{
    public function __construct(private readonly PlayStrategyClient $client) {}

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(config('features.playstrategy.enabled'), 404);

        $state = Str::random(40);
        $codeVerifier = Str::random(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $request->session()->put('playstrategy_oauth', [
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'issued_at' => now()->timestamp,
        ]);

        return redirect()->away(
            $this->client->buildAuthorizeUrl($this->redirectUri(), $codeChallenge, $state),
        );
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(config('features.playstrategy.enabled'), 404);

        /** @var User|null $user */
        $user = $request->user();
        abort_if($user === null, 403);

        $sessionData = $request->session()->pull('playstrategy_oauth');
        $incomingState = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if ($request->filled('error')) {
            return redirect('/profile')->with('error', __('PlayStrategy authorization was cancelled or denied.'));
        }

        if (! is_array($sessionData)
            || ! is_string($sessionData['state'] ?? null)
            || ! hash_equals($sessionData['state'], $incomingState)
            || ! is_string($sessionData['code_verifier'] ?? null)
            || $code === '') {
            return redirect('/profile')->with('error', __('Invalid or expired PlayStrategy OAuth session. Please try connecting again.'));
        }

        try {
            $tokenData = $this->client->exchangeAuthorizationCode(
                $code,
                $sessionData['code_verifier'],
                $this->redirectUri(),
            );
            $account = $this->client->fetchAccount($tokenData['access_token']);

            $linkedToOtherUser = ExternalProviderAccount::query()
                ->where('provider', ExternalProviderAccount::PROVIDER_PLAYSTRATEGY)
                ->where('external_user_id', $account['id'])
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($linkedToOtherUser) {
                return redirect('/profile')->with('error', __('This PlayStrategy account is already linked to another player.'));
            }

            $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : null;

            DB::transaction(function () use ($user, $account, $tokenData, $expiresIn): void {
                $record = ExternalProviderAccount::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'provider' => ExternalProviderAccount::PROVIDER_PLAYSTRATEGY,
                    ],
                    [
                        'uuid' => Str::uuid()->toString(),
                        'external_user_id' => $account['id'],
                        'external_username' => $account['username'],
                        'access_token' => $tokenData['access_token'],
                        'refresh_token' => is_string($tokenData['refresh_token'] ?? null) ? $tokenData['refresh_token'] : null,
                        'scopes' => (string) ($tokenData['scope'] ?? config('services.playstrategy.scopes', 'challenge:write')),
                        'token_expires_at' => $expiresIn !== null && $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
                        'verified_at' => now(),
                        'last_synced_at' => now(),
                    ],
                );

                activity()
                    ->causedBy($user)
                    ->performedOn($record)
                    ->withProperties(['external_user_id' => $account['id'], 'external_username' => $account['username']])
                    ->log('playstrategy_account_linked');
            });

            return redirect('/profile')->with('success', __('Connected PlayStrategy account @:username.', ['username' => $account['username']]));
        } catch (PlayStrategyException $e) {
            return redirect('/profile')->with('error', $e->getMessage());
        }
    }

    public function connectWithToken(Request $request): RedirectResponse
    {
        abort_unless(config('features.playstrategy.enabled'), 404);

        /** @var User|null $user */
        $user = $request->user();
        abort_if($user === null, 403);

        $validated = $request->validate([
            'playstrategy_token' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $accessToken = trim((string) $validated['playstrategy_token']);

        try {
            $account = $this->client->fetchAccount($accessToken);

            $linkedToOtherUser = ExternalProviderAccount::query()
                ->where('provider', ExternalProviderAccount::PROVIDER_PLAYSTRATEGY)
                ->where('external_user_id', $account['id'])
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($linkedToOtherUser) {
                return redirect('/profile')->with('error', __('This PlayStrategy account is already linked to another player.'));
            }

            DB::transaction(function () use ($user, $account, $accessToken): void {
                $record = ExternalProviderAccount::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'provider' => ExternalProviderAccount::PROVIDER_PLAYSTRATEGY,
                    ],
                    [
                        'uuid' => Str::uuid()->toString(),
                        'external_user_id' => $account['id'],
                        'external_username' => $account['username'],
                        'access_token' => $accessToken,
                        'refresh_token' => null,
                        'scopes' => (string) config('services.playstrategy.scopes', 'challenge:write'),
                        'token_expires_at' => null,
                        'verified_at' => now(),
                        'last_synced_at' => now(),
                    ],
                );

                activity()
                    ->causedBy($user)
                    ->performedOn($record)
                    ->withProperties([
                        'external_user_id' => $account['id'],
                        'external_username' => $account['username'],
                        'auth_mode' => 'personal_oauth_token',
                    ])
                    ->log('playstrategy_account_linked');
            });

            return redirect('/profile')->with('success', __('Connected PlayStrategy account @:username.', ['username' => $account['username']]));
        } catch (PlayStrategyException $e) {
            return redirect('/profile')->with('error', __('Could not verify PlayStrategy token: :error', ['error' => $e->getMessage()]));
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if($user === null, 403);

        $account = ExternalProviderAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', ExternalProviderAccount::PROVIDER_PLAYSTRATEGY)
            ->first();

        if ($account !== null) {
            activity()
                ->causedBy($user)
                ->withProperties(['external_user_id' => $account->external_user_id, 'external_username' => $account->external_username])
                ->log('playstrategy_account_unlinked');
            $account->delete();
        }

        return redirect('/profile')->with('success', __('PlayStrategy account disconnected.'));
    }

    private function redirectUri(): string
    {
        $configured = trim((string) config('services.playstrategy.redirect_uri', ''));

        return $configured !== '' ? $configured : route('playstrategy.oauth.callback');
    }
}
