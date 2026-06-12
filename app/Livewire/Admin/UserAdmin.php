<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Actions\AssignRoleAction;
use App\Modules\Identity\Actions\RevokeRoleAction;
use App\Modules\Identity\Actions\SuspendUserAction;
use App\Modules\Identity\Actions\UnsuspendUserAction;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserAdmin extends AdminComponent
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $roleFilter = '';

    // Modals
    public bool $showDetailModal = false;
    public bool $showSuspendModal = false;
    public bool $showRoleModal = false;

    // Selection
    public ?int $selectedUserId = null;

    // Forms
    public string $suspendReason = '';
    public string $selectedRole = '';
    public string $roleAction = 'assign'; // assign | revoke

    protected $paginationTheme = 'tailwind';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function selectUser(int $id): void
    {
        $this->selectedUserId = $id;
        $this->showDetailModal = true;
    }

    public function openSuspendModal(): void
    {
        $this->suspendReason = '';
        $this->showSuspendModal = true;
    }

    public function suspend(SuspendUserAction $action): void
    {
        $this->validate([
            'suspendReason' => 'required|string|min:5|max:255',
        ]);

        if (!$this->selectedUserId) return;

        $target = User::findOrFail($this->selectedUserId);
        $actor = Auth::user();

        if (!$actor) return;

        try {
            $action->execute($target, $actor, $this->suspendReason);
            session()->flash('success', 'User suspended successfully.');
            $this->showSuspendModal = false;
            // Refresh detail modal
        } catch (\Exception $e) {
            session()->flash('error', 'Suspension failed: ' . $e->getMessage());
        }
    }

    public function unsuspend(UnsuspendUserAction $action): void
    {
        if (!$this->selectedUserId) return;

        $target = User::findOrFail($this->selectedUserId);
        $actor = Auth::user();

        if (!$actor) return;

        try {
            $action->execute($target, $actor);
            session()->flash('success', 'User account unsuspended.');
            // Refresh detail modal
        } catch (\Exception $e) {
            session()->flash('error', 'Unsuspension failed: ' . $e->getMessage());
        }
    }

    public function openRoleModal(string $roleAction): void
    {
        $this->roleAction = $roleAction;
        $this->selectedRole = '';
        $this->showRoleModal = true;
    }

    public function updateRole(): void
    {
        $this->validate([
            'selectedRole' => 'required|string',
        ]);

        if (!$this->selectedUserId) return;

        $target = User::findOrFail($this->selectedUserId);
        $actor = Auth::user();

        if (!$actor) return;

        try {
            if ($this->roleAction === 'assign') {
                app(AssignRoleAction::class)->execute($target, $this->selectedRole, $actor);
                session()->flash('success', "Role '{$this->selectedRole}' assigned to user.");
            } else {
                app(RevokeRoleAction::class)->execute($target, $this->selectedRole, $actor);
                session()->flash('success', "Role '{$this->selectedRole}' revoked from user.");
            }
            $this->showRoleModal = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Role update failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = User::query()
            ->with(['roles', 'profile'])
            ->orderBy('created_at', 'desc');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('username', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->roleFilter) {
            $query->whereHas('roles', function ($q) {
                $q->where('name', $this->roleFilter);
            });
        }

        $users = $query->paginate(15);
        $roles = Role::all();

        $selectedUser = null;
        $userKyc = null;
        $walletHistory = [];
        $tournamentHistory = [];

        if ($this->selectedUserId) {
            $selectedUser = User::with(['roles', 'profile', 'wallet'])->find($this->selectedUserId);
            if ($selectedUser) {
                $userKyc = KycSubmission::where('user_id', $this->selectedUserId)
                    ->orderBy('created_at', 'desc')
                    ->first();
                $walletHistory = $selectedUser->wallet
                    ? $selectedUser->wallet->ledgerEntries()->orderBy('created_at', 'desc')->take(10)->get()
                    : [];
                $tournamentHistory = TournamentRegistration::where('user_id', $this->selectedUserId)
                    ->with('tournament')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }

        return view('livewire.admin.user-admin', [
            'users' => $users,
            'roles' => $roles,
            'selectedUser' => $selectedUser,
            'userKyc' => $userKyc,
            'walletHistory' => $walletHistory,
            'tournamentHistory' => $tournamentHistory,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'User Management Directory',
        ]);
    }
}
