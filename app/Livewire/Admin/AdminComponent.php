<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

abstract class AdminComponent extends Component
{
    public function boot(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole([
            'SUPER_ADMIN',
            'ADMIN',
            'MODERATOR',
            'TOURNAMENT_ORGANIZER',
            'SUPPORT_AGENT',
            'FINANCE_OPERATOR',
            'KYC_REVIEWER',
        ])) {
            abort(403, 'Unauthorized access to the admin panel.');
        }
    }
}
