<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionAdmin extends AdminComponent
{
    public $activeRoleId = null;

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
        $allPermissions = Permission::orderBy('name')->get();

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
