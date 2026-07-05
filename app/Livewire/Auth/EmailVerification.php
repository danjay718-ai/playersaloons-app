<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EmailVerification extends Component
{
    public function mount()
    {
        $user = Auth::user();

        if ($user && $user->hasVerifiedEmail()) {
            return redirect()->to('/dashboard');
        }
    }

    public function resend()
    {
        $user = Auth::user();

        if (! $user instanceof MustVerifyEmail) {
            return redirect()->to('/login');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->to('/dashboard');
        }

        $user->sendEmailVerificationNotification();
        session()->flash('message', 'Verification email sent. Please check your inbox.');
    }

    public function render()
    {
        return view('livewire.auth.email-verification')
            ->layout('components.layouts.app', ['title' => 'Verify Email | PlayerSaloons']);
    }
}
