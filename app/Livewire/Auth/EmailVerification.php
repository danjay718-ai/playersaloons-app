<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EmailVerification extends Component
{
    public function verify()
    {
        $user = Auth::user();

        if ($user) {
            $user->update(['email_verified_at' => now()]);
            session()->flash('message', 'Email verified successfully!');

            return redirect()->to('/dashboard');
        }

        return redirect()->to('/login');
    }

    public function render()
    {
        return view('livewire.auth.email-verification')
            ->layout('components.layouts.app', ['title' => 'Verify Email | PlayerSaloons']);
    }
}
