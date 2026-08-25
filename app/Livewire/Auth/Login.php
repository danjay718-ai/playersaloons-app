<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Login extends Component
{
    public string $identity = '';

    public string $password = '';

    public bool $remember = false;

    protected array $rules = [
        'identity' => ['required', 'string'],
        'password' => ['required', 'string'],
    ];

    public function login()
    {
        $this->validate();

        $identity = trim($this->identity);
        $field = filter_var($identity, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $normalizedIdentity = $field === 'email' ? mb_strtolower($identity) : mb_strtolower($identity);
        $user = User::query()->where($field, $identity)->first();
        [$maxAttempts, $lockoutSeconds] = $this->lockoutSettings();
        $accountKey = 'login:account:'.($user?->getKey() ?? hash('sha256', $normalizedIdentity));
        $ipKey = 'login:ip:'.hash('sha256', (string) request()->ip());

        if (RateLimiter::tooManyAttempts($accountKey, $maxAttempts)
            || RateLimiter::tooManyAttempts($ipKey, $maxAttempts * 5)) {
            $this->addLockoutError($accountKey, $ipKey);

            return;
        }

        $credentials = [
            $field => $identity,
            'password' => $this->password,
            'status' => UserStatus::ACTIVE->value,
        ];

        if (Auth::attempt($credentials, $this->remember)) {
            RateLimiter::clear($accountKey);

            $user = Auth::user();
            if ($user?->two_factor_confirmed_at && $user->two_factor_secret) {
                session()->put('two_factor_login', ['id' => $user->id, 'remember' => $this->remember]);
                Auth::logout();
                session()->regenerate();

                return redirect('/two-factor-challenge');
            }

            session()->regenerate();
            $user?->update(['last_login_at' => now()]);
            $adminRoles = ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'FINANCE_OPERATOR', 'KYC_REVIEWER', 'SUPPORT_AGENT', 'TOURNAMENT_ORGANIZER'];

            if ($user && $user->hasAnyRole($adminRoles)) {
                $intended = session('url.intended');
                if ($intended && str_contains((string) $intended, '/admin')) {
                    return redirect()->intended('/admin');
                }

                session()->forget('url.intended');

                return redirect('/admin');
            }

            return redirect()->intended('/dashboard');
        }

        RateLimiter::hit($accountKey, $lockoutSeconds);
        RateLimiter::hit($ipKey, $lockoutSeconds);

        if (RateLimiter::tooManyAttempts($accountKey, $maxAttempts)
            || RateLimiter::tooManyAttempts($ipKey, $maxAttempts * 5)) {
            $this->addLockoutError($accountKey, $ipKey);

            return;
        }

        $this->addError('identity', 'The provided credentials do not match our records.');
    }

    /**
     * @return array{int, int}
     */
    private function lockoutSettings(): array
    {
        $settings = SystemSetting::query()
            ->whereIn('key', ['auth.login_max_attempts', 'auth.login_lockout_minutes'])
            ->pluck('value', 'key');

        $maxAttempts = min(20, max(3, (int) ($settings['auth.login_max_attempts'] ?? 5)));
        $lockoutMinutes = min(1440, max(1, (int) ($settings['auth.login_lockout_minutes'] ?? 15)));

        return [$maxAttempts, $lockoutMinutes * 60];
    }

    private function addLockoutError(string $accountKey, string $ipKey): void
    {
        $seconds = max(
            RateLimiter::availableIn($accountKey),
            RateLimiter::availableIn($ipKey),
        );
        $minutes = max(1, (int) ceil($seconds / 60));

        $this->addError('identity', "Too many sign-in attempts. Try again in {$minutes} minute(s), or reset your password.");
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('components.layouts.app', ['title' => 'Sign In | PlayerSaloons']);
    }
}
