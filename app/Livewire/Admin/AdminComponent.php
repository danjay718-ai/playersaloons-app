<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HandlesUserFacingErrors;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

abstract class AdminComponent extends Component
{
    use HandlesUserFacingErrors;

    /**
     * Get the authenticated admin actor model.
     */
    protected function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function boot(): void
    {
        /** @var User|null $user */
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
