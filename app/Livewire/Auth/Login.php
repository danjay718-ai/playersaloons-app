<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
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

        $field = filter_var($this->identity, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field => $this->identity,
            'password' => $this->password,
        ];

        if (Auth::attempt($credentials, $this->remember)) {
            session()->regenerate();

            $user = Auth::user();
            $adminRoles = ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'FINANCE_OPERATOR', 'KYC_REVIEWER', 'SUPPORT_AGENT', 'TOURNAMENT_ORGANIZER'];

            if ($user && $user->hasAnyRole($adminRoles)) {
                return redirect()->intended('/admin');
            }

            return redirect()->intended('/dashboard');
        }

        $this->addError('identity', 'The provided credentials do not match our records.');
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('components.layouts.app', ['title' => 'Sign In | PlayerSaloons']);
    }
}
