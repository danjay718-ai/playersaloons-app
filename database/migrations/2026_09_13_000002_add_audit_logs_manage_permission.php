<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'audit_logs.manage', 'guard_name' => 'web'],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'audit_logs.manage')
            ->where('guard_name', 'web')
            ->value('id');
        $adminRoleId = DB::table('roles')
            ->where('name', 'ADMIN')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId !== null && $adminRoleId !== null) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // The permission may have been assigned to custom roles after release.
    }
};
