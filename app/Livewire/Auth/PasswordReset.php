<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Modules\Identity\Models\User;
use Livewire\Component;

class PasswordReset extends Component
{
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $isResetMode = false;

    protected array $rules = [
        'email' => ['required', 'email', 'exists:users,email'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ];

    public function requestReset()
    {
        $this->validateOnly('email');
        $this->isResetMode = true;
    }

    public function resetPassword()
    {
        $this->validate();

        $user = User::query()->where('email', $this->email)->first();

        if ($user) {
            $user->update(['password' => bcrypt($this->password)]);
            session()->flash('message', 'Password has been reset successfully!');

            return redirect()->to('/login');
        }

        $this->addError('email', 'Could not locate user.');
    }

    public function render()
    {
        return view('livewire.auth.password-reset')
            ->layout('components.layouts.app', ['title' => 'Reset Password | PlayerSaloons']);
    }
}
