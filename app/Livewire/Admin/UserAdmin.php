<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Modules\Identity\Actions\AssignRoleAction;
use App\Modules\Identity\Actions\RevokeRoleAction;
use App\Modules\Identity\Actions\RegisterUserAction;
use App\Modules\Identity\Actions\SuspendUserAction;
use App\Modules\Identity\Actions\UnsuspendUserAction;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserPresenceService;
use App\Modules\Tournament\Models\TournamentRegistration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserAdmin extends AdminComponent
{
    use WithPagination;

    public string $activeTab = 'players'; // 'players' | 'users'

    public string $search = '';

    public string $statusFilter = '';

    public string $roleFilter = '';

    public string $onlineFilter = '';

    public string $countryFilter = '';

    // Modals
    public bool $showDetailModal = false;

    public bool $showSuspendModal = false;

    public bool $showRoleModal = false;

    public bool $showEditModal = false;

    public bool $showPasswordModal = false;

    public bool $showDeleteModal = false;

    // Selection
    public ?int $selectedUserId = null;

    // Forms
    public string $suspendReason = '';

    public string $selectedRole = '';

    public string $roleAction = 'assign'; // assign | revoke

    // Edit Forms
    public ?int $editingUserId = null;

    public string $editUsername = '';

    public string $editEmail = '';

    public string $editDisplayName = '';

    public string $editCountryCode = '';

    // Password Forms
    public ?int $passwordUserId = null;

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public string $createMode = 'player';

    public string $createUsername = '';

    public string $createEmail = '';

    public string $createDisplayName = '';

    public string $createCountryCode = '';

    public string $createPassword = '';

    public string $createPasswordConfirmation = '';

    public string $createRole = '';

    protected $paginationTheme = 'tailwind';

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

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

    public function updatingOnlineFilter(): void
    {
        $this->resetPage();
    }

    public function updatingCountryFilter(): void
    {
        $this->resetPage();
    }

    public function selectUser(int $id): void
    {
        $this->selectedUserId = $id;
        $this->showDetailModal = true;
    }

    public function createUser(RegisterUserAction $registerUser): void
    {
        abort_unless(Auth::user()?->can('create', User::class), 403);

        $this->validate([
            'createMode' => 'required|in:player,user',
            'createUsername' => 'required|string|max:255|unique:users,username',
            'createEmail' => 'required|email|max:255|unique:users,email',
            'createDisplayName' => 'nullable|string|max:255',
            'createCountryCode' => 'nullable|string|max:2',
            'createPassword' => ['required', 'confirmed', Password::defaults()],
            'createRole' => 'nullable|required_if:createMode,user|exists:roles,name',
        ]);

        if ($this->createMode === 'user' && $this->createRole === 'SUPER_ADMIN' && ! Auth::user()?->hasRole('SUPER_ADMIN')) {
            abort(403);
        }

        $user = $registerUser->execute([
            'username' => $this->createUsername,
            'email' => $this->createEmail,
            'display_name' => $this->createDisplayName ?: $this->createUsername,
            'country_code' => strtoupper($this->createCountryCode),
            'password' => $this->createPassword,
        ]);

        if ($this->createMode === 'user') {
            $user->syncRoles([$this->createRole]);
        }

        session()->flash('success', ucfirst($this->createMode).' account created successfully.');
        $this->dispatch('user-created');
    }

    public function editUser(int $id): void
    {
        $user = User::with('profile')->findOrFail($id);
        abort_unless(Auth::user()?->can('update', $user), 403);

        $this->editingUserId = $user->id;
        $this->editUsername = $user->username;
        $this->editEmail = $user->email;
        $this->editDisplayName = $user->profile->display_name ?? '';
        $this->editCountryCode = $user->profile->country_code ?? '';
        $this->showEditModal = true;
    }

    public function updateUser(): void
    {
        $user = User::findOrFail($this->editingUserId);
        abort_unless(Auth::user()?->can('update', $user), 403);

        $this->validate([
            'editUsername' => 'required|string|max:255|unique:users,username,'.$this->editingUserId,
            'editEmail' => 'required|email|max:255|unique:users,email,'.$this->editingUserId,
            'editDisplayName' => 'nullable|string|max:255',
            'editCountryCode' => 'nullable|string|max:2',
        ]);

        $user->update([
            'username' => $this->editUsername,
            'email' => $this->editEmail,
        ]);

        if ($user->profile) {
            $user->profile->update([
                'display_name' => $this->editDisplayName,
                'country_code' => $this->editCountryCode,
            ]);
        } else {
            $user->profile()->create([
                'uuid' => (string) Str::uuid(),
                'display_name' => $this->editDisplayName,
                'country_code' => $this->editCountryCode,
            ]);
        }

        session()->flash('success', 'User data updated successfully.');
        $this->showEditModal = false;
    }

    public function prepareResetPassword(int $id): void
    {
        $user = User::findOrFail($id);
        abort_unless(Auth::user()?->can('resetPassword', $user), 403);

        $this->passwordUserId = $id;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->showPasswordModal = true;
    }

    public function resetPassword(): void
    {
        $user = User::findOrFail($this->passwordUserId);
        abort_unless(Auth::user()?->can('resetPassword', $user), 403);

        $this->validate([
            'newPassword' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($this->newPassword),
        ]);

        session()->flash('success', 'User password reset successfully.');
        $this->showPasswordModal = false;
    }

    public function confirmDeleteUser(int $id): void
    {
        $user = User::findOrFail($id);
        abort_unless(Auth::user()?->can('delete', $user), 403);

        $this->selectedUserId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        $user = User::findOrFail($this->selectedUserId);
        $actor = Auth::user();
        abort_unless($actor?->can('delete', $user), 403);

        if ($user->is($actor)) {
            session()->flash('error', 'You cannot delete your own account.');

            return;
        }

        if ($user->hasRole('SUPER_ADMIN') && User::role('SUPER_ADMIN')->count() <= 1) {
            session()->flash('error', 'The only SUPER_ADMIN account cannot be deleted.');

            return;
        }

        $user->delete();
        $this->showDeleteModal = false;
        $this->selectedUserId = null;
        session()->flash('success', 'User account deleted successfully.');
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

        if (! $this->selectedUserId) {
            return;
        }

        $target = User::findOrFail($this->selectedUserId);
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        if ($target->id === $actor->id) {
            session()->flash('error', 'Suspension failed: You cannot suspend your own account.');

            return;
        }

        try {
            $action->execute($target, $actor, $this->suspendReason);
            session()->flash('success', 'User suspended successfully.');
            $this->showSuspendModal = false;
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to suspend the user.'));
        }
    }

    public function unsuspend(UnsuspendUserAction $action): void
    {
        if (! $this->selectedUserId) {
            return;
        }

        $target = User::findOrFail($this->selectedUserId);
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        try {
            $action->execute($target, $actor);
            session()->flash('success', 'User account unsuspended.');
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to restore the user.'));
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

        if (! $this->selectedUserId) {
            return;
        }

        $target = User::findOrFail($this->selectedUserId);
        $actor = Auth::user();

        if (! $actor) {
            return;
        }

        try {
            if ($this->roleAction === 'revoke' && $target->id === $actor->id && $this->selectedRole === 'SUPER_ADMIN') {
                $superAdminCount = User::role('SUPER_ADMIN')->count();
                if ($superAdminCount <= 1) {
                    throw new \Exception('You cannot revoke your own SUPER_ADMIN role because you are the only one. Assign it to someone else first.');
                }
            }

            if ($this->roleAction === 'assign') {
                app(AssignRoleAction::class)->execute($target, $this->selectedRole, $actor);
                session()->flash('success', "Role '{$this->selectedRole}' assigned to user.");
            } else {
                app(RevokeRoleAction::class)->execute($target, $this->selectedRole, $actor);
                session()->flash('success', "Role '{$this->selectedRole}' revoked from user.");
            }
            $this->showRoleModal = false;
        } catch (\Exception $e) {
            session()->flash('error', $this->safeError($e, 'Unable to update the user role.'));
        }
    }

    public function render(UserPresenceService $presence)
    {
        // One sorted-set read serves filtering and every row indicator. This
        // avoids Redis KEYS (blocking at scale) and per-row presence calls.
        $onlineIds = $presence->onlineUserIds();

        // Build players query for count
        $playersQuery = User::query()->whereHas('roles', function ($q) {
            $q->where('name', 'PLAYER');
        });

        if ($this->onlineFilter !== '') {
            if ($this->onlineFilter === 'online') {
                $playersQuery->whereIn('id', $onlineIds);
            } elseif ($this->onlineFilter === 'offline') {
                $playersQuery->whereNotIn('id', $onlineIds);
            }
        }

        if ($this->countryFilter !== '') {
            $playersQuery->whereHas('profile', function ($q) {
                $q->where('country_code', $this->countryFilter);
            });
        }

        $playersCount = $playersQuery->count();

        // Build users query for count
        $usersQuery = User::query()->whereDoesntHave('roles', function ($q) {
            $q->where('name', 'PLAYER');
        });

        if ($this->search) {
            $usersQuery->where(function ($q) {
                $q->where('username', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        }
        if ($this->statusFilter) {
            $usersQuery->where('status', $this->statusFilter);
        }
        if ($this->roleFilter) {
            $usersQuery->whereHas('roles', function ($q) {
                $q->where('name', $this->roleFilter);
            });
        }

        $usersCount = $usersQuery->count();

        // Main query for active tab
        $query = $this->activeTab === 'players' ? clone $playersQuery : clone $usersQuery;
        $query->with(['roles', 'profile'])->orderBy('created_at', 'desc');

        $users = $query->paginate(15);
        $users->getCollection()->each(
            fn (User $user) => $user->setAttribute('is_online', in_array($user->id, $onlineIds, true)),
        );
        $roles = Role::all();

        $selectedUser = null;
        $userKyc = null;
        $walletHistory = [];
        $tournamentHistory = [];

        if ($this->selectedUserId && $this->showDetailModal) {
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
            'playersCount' => $playersCount,
            'usersCount' => $usersCount,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'User Management Directory',
        ]);
    }
}
