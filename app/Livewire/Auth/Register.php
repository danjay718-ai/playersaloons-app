<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Modules\Identity\Actions\RegisterUserAction;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Register extends Component
{
    public string $username = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $display_name = '';

    protected array $rules = [
        'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:30', 'unique:users,username'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'display_name' => ['nullable', 'string', 'max:100'],
    ];

    public function register(RegisterUserAction $action)
    {
        $this->validate();

        $user = $action->execute([
            'email' => $this->email,
            'username' => $this->username,
            'password' => $this->password,
            'display_name' => $this->display_name ?: null,
        ]);

        Auth::login($user);

        return redirect()->to('/dashboard');
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layout('components.layouts.app', ['title' => 'Register | PlayerSaloons']);
    }
}
