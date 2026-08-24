<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

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
        abort_unless($this->actor()->hasAnyRole(['SUPER_ADMIN', 'ADMIN']), 403);
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

    public function createRole(): void
    {
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
    }

    public function openEditRole(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            session()->flash('error', "The system role '{$role->name}' cannot be renamed.");

            return;
        }

        $this->editingRoleId = $role->id;
        $this->editRoleName = $role->name;
        $this->showEditModal = true;
    }

    public function updateRoleName(): void
    {
        if (! $this->editingRoleId) {
            return;
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
    }

    public function openDeleteRole(int $roleId): void
    {
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

    public function confirmDeleteRole(): void
    {
        if (! $this->deletingRoleId) {
            return;
        }

        $role = Role::findOrFail($this->deletingRoleId);

        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            session()->flash('error', "The system role '{$role->name}' cannot be deleted.");
            $this->showDeleteModal = false;

            return;
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            session()->flash('error', "Cannot delete role '{$role->name}' because it is currently assigned to {$userCount} user(s). Reassign them first.");
            $this->showDeleteModal = false;

            return;
        }

        $roleName = $role->name;
        $role->delete();

        $this->showDeleteModal = false;
        $this->deletingRoleId = null;

        $firstRole = Role::orderBy('name')->first();
        $this->activeRoleId = $firstRole?->id;

        session()->flash('success', "Role '{$roleName}' was permanently deleted.");
    }

    public function togglePermission($permissionName)
    {
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

        // Group permissions based on prefixes (e.g., 'manage_users' -> 'manage' or 'user')
        // We'll group by the first word before an underscore or dash.
        $groupedPermissions = [];
        foreach ($allPermissions as $perm) {
            $parts = preg_split('/[_\\-]/', $perm->name, 2);
            $group = count($parts) > 1 ? $parts[0] : 'General';
            $groupedPermissions[ucfirst(strtolower($group))][] = $perm;
        }
        
        // Sort groups alphabetically
        ksort($groupedPermissions);

        return view('livewire.admin.role-permission-admin', [
            'roles' => $roles,
            'groupedPermissions' => $groupedPermissions,
        ])->layout('components.layouts.admin', [
            'admin_title' => 'Roles & Permissions',
        ]);
    }
}
