<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Exceptions\GoogleOAuthException;
use App\Modules\Identity\Models\ExternalProviderAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\GoogleOAuthClient;
use App\Shared\Enums\UserStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleOAuthController extends Controller
{
    private const ADMIN_ROLES = [
        'SUPER_ADMIN',
        'ADMIN',
        'MODERATOR',
        'FINANCE_OPERATOR',
        'KYC_REVIEWER',
        'SUPPORT_AGENT',
        'TOURNAMENT_ORGANIZER',
    ];

    public function __construct(private readonly GoogleOAuthClient $client) {}

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(config('features.google_auth.enabled'), 404);

        $isLinking = $request->user() !== null;
        $fallbackUrl = $isLinking ? '/profile' : '/login';

        if (! $this->client->isConfigured()) {
            return redirect($fallbackUrl)->with('error', __('Google sign-in is not configured yet. Please use email and password or contact support.'));
        }

        $state = Str::random(40);
        $codeVerifier = Str::random(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $ref = $request->query('ref');
        $referrerId = is_numeric($ref) && (int) $ref > 0 ? (int) $ref : null;

        $request->session()->put('google_oauth', [
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'intent' => $isLinking ? 'link' : 'login',
            'referrer_id' => $referrerId,
            'issued_at' => now()->timestamp,
        ]);

        return redirect()->away(
            $this->client->buildAuthorizeUrl($this->redirectUri(), $codeChallenge, $state),
        );
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(config('features.google_auth.enabled'), 404);

        $sessionData = $request->session()->pull('google_oauth');
        $intent = is_array($sessionData) && ($sessionData['intent'] ?? null) === 'link' ? 'link' : 'login';
        $fallbackUrl = ($intent === 'link' || $request->user() !== null) ? '/profile' : '/login';

        if ($request->filled('error')) {
            return redirect($fallbackUrl)->with('error', __('Google sign-in was cancelled or denied.'));
        }

        $incomingState = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if (! is_array($sessionData)
            || ! is_string($sessionData['state'] ?? null)
            || ! hash_equals($sessionData['state'], $incomingState)
            || ! is_string($sessionData['code_verifier'] ?? null)
            || $code === '') {
            return redirect($fallbackUrl)->with('error', __('Invalid or expired Google sign-in session. Please try again.'));
        }

        if (! $this->client->isConfigured()) {
            return redirect($fallbackUrl)->with('error', __('Google sign-in is not configured yet.'));
        }

        try {
            $tokenData = $this->client->exchangeAuthorizationCode(
                $code,
                $sessionData['code_verifier'],
                $this->redirectUri(),
            );
            $googleUser = $this->client->fetchUserInfo($tokenData['access_token']);
        } catch (GoogleOAuthException $e) {
            return redirect($fallbackUrl)->with('error', $e->getMessage());
        }

        if (! $googleUser['email_verified']) {
            return redirect($fallbackUrl)->with('error', __('Your Google account email must be verified before signing in.'));
        }

        /** @var User|null $authenticatedUser */
        $authenticatedUser = $request->user();
        if ($authenticatedUser !== null) {
            return $this->linkToAuthenticatedUser($authenticatedUser, $googleUser, $tokenData);
        }

        return $this->authenticateOrStartRegistration($request, $googleUser, $tokenData, $sessionData['referrer_id'] ?? null);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if($user === null, 403);

        $account = ExternalProviderAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', ExternalProviderAccount::PROVIDER_GOOGLE)
            ->first();

        if ($account !== null) {
            activity()
                ->causedBy($user)
                ->withProperties([
                    'external_user_id' => $account->external_user_id,
                    'external_username' => $account->external_username,
                ])
                ->log('google_account_unlinked');
            $account->delete();
        }

        return redirect('/profile')->with('success', __('Google account disconnected.'));
    }

    /**
     * @param  array{sub: string, email: string, email_verified: bool, name: string, picture: string|null}  $googleUser
     * @param  array{access_token: string, refresh_token?: string, expires_in?: int, scope?: string, token_type?: string}  $tokenData
     */
    private function linkToAuthenticatedUser(User $user, array $googleUser, array $tokenData): RedirectResponse
    {
        $linkedToOtherUser = ExternalProviderAccount::query()
            ->where('provider', ExternalProviderAccount::PROVIDER_GOOGLE)
            ->where('external_user_id', $googleUser['sub'])
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($linkedToOtherUser) {
            return redirect('/profile')->with('error', __('This Google account is already linked to another player.'));
        }

        $this->persistGoogleAccount($user, $googleUser, $tokenData);

        return redirect('/profile')->with('success', __('Connected Google account (:email).', ['email' => $googleUser['email']]));
    }

    /**
     * @param  array{sub: string, email: string, email_verified: bool, name: string, picture: string|null}  $googleUser
     * @param  array{access_token: string, refresh_token?: string, expires_in?: int, scope?: string, token_type?: string}  $tokenData
     */
    private function authenticateOrStartRegistration(
        Request $request,
        array $googleUser,
        array $tokenData,
        ?int $referrerId,
    ): RedirectResponse {
        $linkedAccount = ExternalProviderAccount::query()
            ->with('user')
            ->where('provider', ExternalProviderAccount::PROVIDER_GOOGLE)
            ->where('external_user_id', $googleUser['sub'])
            ->first();

        if ($linkedAccount !== null && $linkedAccount->user instanceof User) {
            $user = $linkedAccount->user;

            if ($rejection = $this->statusRejectionMessage($user)) {
                return redirect('/login')->with('error', $rejection);
            }

            $this->persistGoogleAccount($user, $googleUser, $tokenData);

            return $this->completeLogin($request, $user);
        }

        $existingUser = User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$googleUser['email']])
            ->first();

        if ($existingUser instanceof User) {
            if ($existingUser->trashed()) {
                return redirect('/login')->with('error', __('An archived or deleted account already uses this email address. Contact support to restore it or use a different Google account.'));
            }

            if ($rejection = $this->statusRejectionMessage($existingUser)) {
                return redirect('/login')->with('error', $rejection);
            }

            if ($existingUser->hasAnyRole(self::ADMIN_ROLES)) {
                return redirect('/login')->with('error', __('Admin accounts must sign in with email and password.'));
            }

            $existingGoogleLink = $existingUser->googleAccount()->first();
            if ($existingGoogleLink !== null && $existingGoogleLink->external_user_id !== $googleUser['sub']) {
                return redirect('/login')->with('error', __('This email is already linked to a different Google account.'));
            }

            $this->persistGoogleAccount($existingUser, $googleUser, $tokenData);

            if ($existingUser->email_verified_at === null) {
                $existingUser->forceFill(['email_verified_at' => now()])->save();
            }

            return $this->completeLogin($request, $existingUser);
        }

        // Brand-new player: collect country, username, legal name, and 18+/policy consent first.
        $request->session()->put('google_oauth_pending_registration', [
            'sub' => $googleUser['sub'],
            'email' => $googleUser['email'],
            'name' => $googleUser['name'],
            'access_token' => $tokenData['access_token'],
            'refresh_token' => is_string($tokenData['refresh_token'] ?? null) ? $tokenData['refresh_token'] : null,
            'scopes' => (string) ($tokenData['scope'] ?? 'openid email profile'),
            'expires_in' => isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : null,
            'referrer_id' => $referrerId,
            'issued_at' => now()->timestamp,
        ]);

        return redirect()->route('auth.google.complete');
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        if ($user->two_factor_confirmed_at && $user->two_factor_secret) {
            $request->session()->put('two_factor_login', ['id' => $user->id, 'remember' => true]);
            $request->session()->regenerate();

            return redirect('/two-factor-challenge');
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        if ($user->hasAnyRole(self::ADMIN_ROLES)) {
            $intended = session('url.intended');
            if ($intended && str_contains((string) $intended, '/admin')) {
                return redirect()->intended('/admin');
            }

            session()->forget('url.intended');

            return redirect('/admin');
        }

        return redirect()->intended('/dashboard');
    }

    private function statusRejectionMessage(User $user): ?string
    {
        return match ($user->status) {
            UserStatus::BANNED => __('Your account is banned. Contact support for assistance.'),
            UserStatus::SUSPENDED => __('Your account is suspended. Contact support for assistance.'),
            UserStatus::ACTIVE => null,
        };
    }

    /**
     * @param  array{sub: string, email: string, email_verified: bool, name: string, picture: string|null}  $googleUser
     * @param  array{access_token: string, refresh_token?: string, expires_in?: int, scope?: string, token_type?: string}  $tokenData
     */
    private function persistGoogleAccount(User $user, array $googleUser, array $tokenData): void
    {
        $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : null;

        DB::transaction(function () use ($user, $googleUser, $tokenData, $expiresIn): void {
            $record = ExternalProviderAccount::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => ExternalProviderAccount::PROVIDER_GOOGLE,
                ],
                [
                    'uuid' => Str::uuid()->toString(),
                    'external_user_id' => $googleUser['sub'],
                    'external_username' => $googleUser['email'],
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => is_string($tokenData['refresh_token'] ?? null) ? $tokenData['refresh_token'] : null,
                    'scopes' => (string) ($tokenData['scope'] ?? 'openid email profile'),
                    'token_expires_at' => $expiresIn !== null && $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
                    'verified_at' => now(),
                    'last_synced_at' => now(),
                ],
            );

            activity()
                ->causedBy($user)
                ->performedOn($record)
                ->withProperties([
                    'external_user_id' => $googleUser['sub'],
                    'external_username' => $googleUser['email'],
                ])
                ->log('google_account_linked');
        });
    }

    private function redirectUri(): string
    {
        $configured = trim((string) config('services.google.redirect_uri', ''));

        return $configured !== '' ? $configured : route('auth.google.callback');
    }
}
