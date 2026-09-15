<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Spatie\Permission\Models\Permission;
use App\Modules\Identity\Models\Role;

class RolePermissionAdmin extends AdminComponent
{
    /** @var array<int, string> */
    public const PROTECTED_ROLES = [
        'SUPER_ADMIN',
        'ADMIN',
        'PLAYER',
        'TEAM_CAPTAIN',
    ];

    /** @var array<int, string> */
    private const PLAYER_PERMISSION_NAMES = [
        'tournaments.view',
        'tournaments.register',
        'matches.view',
        'matches.submit_result',
        'disputes.open',
        'teams.view',
        'teams.create',
        'teams.manage',
        'teams.invite',
        'teams.remove_member',
        'wallets.view',
        'wallets.request_withdrawal',
        'cms.view',
        'games.view',
    ];

    public ?int $activeRoleId = null;

    // Modals
    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    // Form inputs
    public string $newRoleName = '';

    public ?int $editingRoleId = null;

    public string $editRoleName = '';

    public ?int $deletingRoleId = null;

    public function boot(): void
    {
        parent::boot();
        abort_unless($this->actor()->can('roles.view'), 403);
    }

    public function mount()
    {
        $firstRole = Role::orderBy('name')->first();
        if ($firstRole) {
            $this->activeRoleId = $firstRole->id;
        }
    }

    public function setActiveRole($id)
    {
        $this->activeRoleId = $id;
    }

    public function openCreateRole(): void
    {
        $this->newRoleName = '';
        $this->showCreateModal = true;
    }

    public function createRole(?string $name = null): void
    {
        $this->authorizeRoleManagement();
        if ($name !== null) {
            $this->newRoleName = $name;
        }

        $this->validate([
            'newRoleName' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Z0-9_]+$/i', 'unique:roles,name'],
        ], [
            'newRoleName.regex' => 'The role name must contain only letters, numbers, and underscores.',
            'newRoleName.unique' => 'A role with this name already exists.',
        ]);

        $formattedName = strtoupper(trim($this->newRoleName));

        $role = Role::create([
            'name' => $formattedName,
            'guard_name' => 'web',
        ]);

        $this->activeRoleId = $role->id;
        $this->newRoleName = '';
        $this->showCreateModal = false;

        session()->flash('success', "Role '{$formattedName}' created successfully.");
        $this->dispatch('role-created');
    }

    public function openEditRole(int $roleId): void
    {
        $this->authorizeRoleManagement();
        $role = Role::findOrFail($roleId);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            session()->flash('error', "The system role '{$role->name}' cannot be renamed.");

            return;
        }

        $this->editingRoleId = $role->id;
        $this->editRoleName = $role->name;
        $this->showEditModal = true;
    }

    public function updateRoleName(?int $id = null, ?string $name = null): void
    {
        $this->authorizeRoleManagement();
        $targetId = $id ?? $this->editingRoleId;
        if (! $targetId) {
            return;
        }
        $this->editingRoleId = $targetId;

        if ($name !== null) {
            $this->editRoleName = $name;
        }

        $role = Role::findOrFail($this->editingRoleId);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            session()->flash('error', "The system role '{$role->name}' cannot be renamed.");
            $this->showEditModal = false;

            return;
        }

        $this->validate([
            'editRoleName' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[A-Z0-9_]+$/i', 'unique:roles,name,'.$role->id],
        ], [
            'editRoleName.regex' => 'The role name must contain only letters, numbers, and underscores.',
            'editRoleName.unique' => 'A role with this name already exists.',
        ]);

        $oldName = $role->name;
        $newName = strtoupper(trim($this->editRoleName));

        $role->update(['name' => $newName]);

        $this->showEditModal = false;
        $this->editingRoleId = null;
        $this->editRoleName = '';

        session()->flash('success', "Role '{$oldName}' was renamed to '{$newName}'.");
        $this->dispatch('role-updated');
    }

    public function openDeleteRole(int $roleId): void
    {
        $this->authorizeRoleManagement();
        $role = Role::findOrFail($roleId);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            session()->flash('error', "The system role '{$role->name}' cannot be deleted.");

            return;
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            session()->flash('error', "Cannot delete role '{$role->name}' because it is currently assigned to {$userCount} user(s). Reassign them first.");

            return;
        }

        $this->deletingRoleId = $role->id;
        $this->showDeleteModal = true;
    }

    public function confirmDeleteRole(?int $id = null): void
    {
        $this->authorizeRoleManagement();
        $targetId = $id ?? $this->deletingRoleId;
        if (! $targetId) {
            return;
        }
        $this->deletingRoleId = $targetId;

        $role = Role::findOrFail($this->deletingRoleId);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            session()->flash('error', "The system role '{$role->name}' cannot be deleted.");
            $this->showDeleteModal = false;

            return;
        }

        if ($role->users()->exists()) {
            session()->flash('error', "Cannot delete role '{$role->name}' because it is currently assigned to one or more user(s). Reassign them first.");
            $this->showDeleteModal = false;

            return;
        }

        $roleName = $role->name;
        app(\App\Modules\Operations\Services\AdminDeletionService::class)->delete('roles', [$role->id], $this->actor());

        $this->showDeleteModal = false;
        $this->deletingRoleId = null;

        $firstRole = Role::orderBy('name')->first();
        $this->activeRoleId = $firstRole?->id;

        session()->flash('success', "Role '{$roleName}' was deleted. Its permission assignments remain stored.");
        $this->dispatch('role-deleted');
    }

    public function togglePermission($permissionName)
    {
        $this->authorizeRoleManagement();
        $role = Role::findById($this->activeRoleId);

        if ($role->name === 'SUPER_ADMIN') {
            session()->flash('error', 'Modification of SUPER_ADMIN permissions is restricted.');

            return;
        }

        if ($role->hasPermissionTo($permissionName)) {
            $role->revokePermissionTo($permissionName);
            session()->flash('success', "Permission '{$permissionName}' revoked from role '{$role->name}'.");
        } else {
            $role->givePermissionTo($permissionName);
            session()->flash('success', "Permission '{$permissionName}' granted to role '{$role->name}'.");
        }
    }

    public function render()
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $activeRole = $roles->firstWhere('id', $this->activeRoleId);
        $allPermissions = Permission::query()
            ->when($activeRole?->name === 'PLAYER', fn ($query) => $query->whereIn('name', self::PLAYER_PERMISSION_NAMES))
            ->orderBy('name')
            ->get();

        // Permission names follow resource.action. Group by resource so the
        // matrix mirrors the real admin pages and CRUD domains.
        $groupedPermissions = [];
        foreach ($allPermissions as $perm) {
            $group = explode('.', $perm->name, 2)[0] ?: 'General';
            $groupedPermissions[ucfirst(strtolower($group))][] = $perm;
        }

        // Sort groups alphabetically
        ksort($groupedPermissions);

        return view('livewire.admin.role-permission-admin', [
            'roles' => $roles,
            'groupedPermissions' => $groupedPermissions,
            'canManageRoles' => $this->actor()->can('roles.manage'),
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Roles & Permissions',
        ]);
    }

    private function authorizeRoleManagement(): void
    {
        abort_unless($this->actor()->can('roles.manage'), 403);
    }
}
