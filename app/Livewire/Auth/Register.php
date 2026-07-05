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

    public bool $accepted_policies = false;

    public bool $age_confirmed = false;

    public bool $newsletter_subscribed = false;

    protected array $rules = [
        'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:30', 'unique:users,username'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'display_name' => ['nullable', 'string', 'max:100'],
        'accepted_policies' => ['accepted'],
        'age_confirmed' => ['accepted'],
    ];

    protected array $messages = [
        'accepted_policies.accepted' => 'You must accept the Cookie Policy, Terms & Conditions, and Privacy Policy to create an account.',
        'age_confirmed.accepted' => 'You must confirm that you are 18 years or older to create an account.',
    ];

    public function register(RegisterUserAction $action)
    {
        $this->validate();

        $acceptedAt = now();

        $user = $action->execute([
            'email' => $this->email,
            'username' => $this->username,
            'password' => $this->password,
            'display_name' => $this->display_name ?: null,
            'accepted_terms_at' => $acceptedAt,
            'accepted_privacy_policy_at' => $acceptedAt,
            'accepted_cookie_policy_at' => $acceptedAt,
            'age_confirmed_at' => $acceptedAt,
            'newsletter_subscribed' => $this->newsletter_subscribed,
            'newsletter_subscribed_at' => $this->newsletter_subscribed ? $acceptedAt : null,
            'policy_acceptance_ip' => request()->ip(),
            'policy_acceptance_user_agent' => substr((string) request()->userAgent(), 0, 2000),
        ]);

        Auth::login($user);
        $user->sendEmailVerificationNotification();

        return redirect()->to('/verify-email');
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layout('components.layouts.app', ['title' => 'Register | PlayerSaloons']);
    }
}
