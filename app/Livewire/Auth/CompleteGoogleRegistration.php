<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Modules\Compliance\Services\CountryEligibilityService;
use App\Modules\Identity\Actions\RegisterUserAction;
use App\Modules\Identity\Models\ExternalProviderAccount;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CompleteGoogleRegistration extends Component
{
    public string $googleEmail = '';

    public string $username = '';

    public string $full_name = '';

    public string $countryCode = '';

    public bool $accepted_policies = false;

    public bool $age_confirmed = false;

    public bool $newsletter_subscribed = false;

    protected array $messages = [
        'accepted_policies.accepted' => 'You must accept the Cookie Policy, Terms & Conditions, and Privacy Policy to create an account.',
        'age_confirmed.accepted' => 'You must confirm that you are 18 years or older to create an account.',
    ];

    public function mount(): void
    {
        abort_unless(config('features.google_auth.enabled'), 404);

        $pending = $this->pendingRegistration();
        if ($pending === null) {
            session()->flash('error', __('Your Google sign-up session expired. Please continue with Google again.'));
            $this->redirect('/register');

            return;
        }

        $this->googleEmail = $pending['email'];
        $this->full_name = $this->sanitizeLegalName($pending['name']);
        $this->username = $this->suggestUsername($pending['name'], $pending['email']);
    }

    public function complete(RegisterUserAction $action)
    {
        $pending = $this->pendingRegistration();
        if ($pending === null) {
            session()->flash('error', __('Your Google sign-up session expired. Please continue with Google again.'));

            return redirect('/register');
        }

        $countryCodes = array_keys(app(CountryEligibilityService::class)->selectableCountries());

        $validated = $this->validate([
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:30', 'unique:users,username'],
            'full_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[\pL\pM][\pL\pM .\'\-]*$/u'],
            'countryCode' => ['required', 'string', 'size:2', Rule::in($countryCodes)],
            'accepted_policies' => ['accepted'],
            'age_confirmed' => ['accepted'],
            'newsletter_subscribed' => ['boolean'],
        ]);

        $existingByEmail = User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$pending['email']])
            ->first();

        if ($existingByEmail !== null) {
            session()->forget('google_oauth_pending_registration');

            return redirect('/login')->with('error', $existingByEmail->trashed()
                ? __('An archived or deleted account already uses this email address. Contact support to restore it or use a different Google account.')
                : __('An account with this email already exists. Please sign in with Google again.'));
        }

        $alreadyLinked = ExternalProviderAccount::query()
            ->where('provider', ExternalProviderAccount::PROVIDER_GOOGLE)
            ->where('external_user_id', $pending['sub'])
            ->exists();

        if ($alreadyLinked) {
            session()->forget('google_oauth_pending_registration');

            return redirect('/login')->with('error', __('This Google account is already registered. Please sign in instead.'));
        }

        $acceptedAt = now();

        $user = $action->execute([
            'email' => $pending['email'],
            'username' => trim($validated['username']),
            'password' => Str::random(48),
            'full_name' => trim($validated['full_name']),
            'display_name' => trim($validated['username']),
            'accepted_terms_at' => $acceptedAt,
            'accepted_privacy_policy_at' => $acceptedAt,
            'accepted_cookie_policy_at' => $acceptedAt,
            'age_confirmed_at' => $acceptedAt,
            'newsletter_subscribed' => $this->newsletter_subscribed,
            'newsletter_subscribed_at' => $this->newsletter_subscribed ? $acceptedAt : null,
            'country_code' => $validated['countryCode'],
            'policy_acceptance_ip' => request()->ip(),
            'policy_acceptance_user_agent' => substr((string) request()->userAgent(), 0, 2000),
            'referrer_id' => $pending['referrer_id'],
        ]);

        $expiresIn = $pending['expires_in'];

        DB::transaction(function () use ($user, $pending, $expiresIn): void {
            $user->forceFill([
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ])->save();

            $record = ExternalProviderAccount::query()->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'provider' => ExternalProviderAccount::PROVIDER_GOOGLE,
                'external_user_id' => $pending['sub'],
                'external_username' => $pending['email'],
                'access_token' => $pending['access_token'],
                'refresh_token' => $pending['refresh_token'],
                'scopes' => $pending['scopes'],
                'token_expires_at' => $expiresIn !== null && $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
                'verified_at' => now(),
                'last_synced_at' => now(),
            ]);

            activity()
                ->causedBy($user)
                ->performedOn($record)
                ->withProperties([
                    'external_user_id' => $pending['sub'],
                    'external_username' => $pending['email'],
                ])
                ->log('google_account_registered');
        });

        session()->forget('google_oauth_pending_registration');

        Auth::login($user, true);
        session()->regenerate();

        return redirect()->to('/dashboard');
    }

    /**
     * @return array{sub: string, email: string, name: string, access_token: string, refresh_token: string|null, scopes: string, expires_in: int|null, referrer_id: int|null, issued_at: int}|null
     */
    private function pendingRegistration(): ?array
    {
        $data = session('google_oauth_pending_registration');
        if (! is_array($data)) {
            return null;
        }

        $issuedAt = (int) ($data['issued_at'] ?? 0);
        if ($issuedAt <= 0 || (now()->timestamp - $issuedAt) > 900) {
            session()->forget('google_oauth_pending_registration');

            return null;
        }

        $sub = trim((string) ($data['sub'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $accessToken = (string) ($data['access_token'] ?? '');

        if ($sub === '' || $email === '' || $accessToken === '') {
            return null;
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => trim((string) ($data['name'] ?? '')),
            'access_token' => $accessToken,
            'refresh_token' => is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            'scopes' => (string) ($data['scopes'] ?? 'openid email profile'),
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            'referrer_id' => isset($data['referrer_id']) && is_numeric($data['referrer_id']) ? (int) $data['referrer_id'] : null,
            'issued_at' => $issuedAt,
        ];
    }

    private function sanitizeLegalName(string $name): string
    {
        $cleaned = trim((string) preg_replace('/[^\pL\pM .\'\-]+/u', ' ', $name));
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $cleaned));

        return mb_substr($cleaned, 0, 150);
    }

    private function suggestUsername(string $name, string $email): string
    {
        $localPart = explode('@', $email)[0];
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $localPart !== '' ? $localPart : $name) ?? '';
        $base = trim($base, '_-');
        if (strlen($base) < 3) {
            $base = 'player_'.strtolower(Str::random(5));
        }

        $base = substr($base, 0, 24);
        $candidate = $base;
        $suffix = 1;

        while (User::withTrashed()->where('username', $candidate)->exists()) {
            $candidate = substr($base, 0, 24).'_'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    public function render()
    {
        return view('livewire.auth.complete-google-registration', [
            'countries' => app(CountryEligibilityService::class)->selectableCountries(),
        ])->layout('components.layouts.auth', ['title' => __('Complete Google Sign-Up').' | GamersRival']);
    }
}
