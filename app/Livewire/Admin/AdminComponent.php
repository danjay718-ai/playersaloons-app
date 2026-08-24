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
        if (! $user) {
            abort(403, 'Unauthorized access to the admin panel.');
        }

        $hasStaffRole = $user->roles->whereNotIn('name', ['PLAYER', 'TEAM_CAPTAIN'])->isNotEmpty();
        if (! $hasStaffRole && ! $user->hasRole('SUPER_ADMIN')) {
            abort(403, 'Unauthorized access to the admin panel.');
        }
    }
}
