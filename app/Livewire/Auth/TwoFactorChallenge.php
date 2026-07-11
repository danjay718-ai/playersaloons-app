<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TotpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class TwoFactorChallenge extends Component
{
    public string $code = '';

    public bool $useRecoveryCode = false;

    public function mount(): void
    {
        if (! session()->has('two_factor_login.id')) {
            $this->redirect('/login');
        }
    }

    public function verify(TotpService $totp)
    {
        $this->validate(['code' => ['required', 'string', $this->useRecoveryCode ? 'max:20' : 'digits:6']]);

        $user = User::find(session('two_factor_login.id'));
        if (! $user || ! $user->two_factor_secret || ! $user->two_factor_confirmed_at) {
            session()->forget('two_factor_login');

            return redirect('/login');
        }

        $valid = $this->useRecoveryCode
            ? $this->consumeRecoveryCode($user, $this->code)
            : $totp->verify($user->two_factor_secret, $this->code);

        if (! $valid) {
            $this->addError('code', 'The authentication code is invalid.');

            return null;
        }

        $remember = (bool) session('two_factor_login.remember', false);
        Auth::login($user, $remember);
        session()->forget('two_factor_login');
        session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect()->intended($user->hasRole('PLAYER') ? '/dashboard' : '/admin');
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $index => $hash) {
            if (Hash::check(strtolower(trim($code)), $hash)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge')
            ->layout('components.layouts.app', ['title' => 'Two-Factor Authentication | PlayerSaloons']);
    }
}
