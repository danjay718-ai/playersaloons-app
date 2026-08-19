<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\PasswordReset as PasswordResetEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Component;

class PasswordReset extends Component
{
    public string $email = '';

    public string $token = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $isResetMode = false;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required_if:isResetMode,true', 'string'],
            'password' => ['required_if:isResetMode,true', 'string', 'confirmed', PasswordRule::defaults()],
        ];
    }

    public function mount(?string $token = null): void
    {
        if ($token !== null) {
            $this->token = $token;
            $this->email = (string) request()->query('email', '');
            $this->isResetMode = true;
        }
    }

    public function requestReset()
    {
        $this->validateOnly('email');

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('message', __($status));

            return;
        }

        $this->addError('email', __($status));
    }

    public function resetPassword()
    {
        $this->validate();

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordResetEvent($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('message', __($status));

            return redirect()->to('/login');
        }

        $this->addError('email', __($status));
    }

    public function render()
    {
        return view('livewire.auth.password-reset')
            ->layout('components.layouts.app', ['title' => 'Reset Password | PlayerSaloons']);
    }
}
