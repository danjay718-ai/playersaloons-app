<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Makes the expanded role matrix available to existing installations.
     * The canonical catalog remains RolesAndPermissionsSeeder; this migration
     * only backfills deployments that already ran the original seeder.
     */
    public function up(): void
    {
        $permissions = [
            'staff_activity.view', 'roles.view', 'roles.manage',
            'translations.view', 'translations.manage',
            'advertisements.view', 'advertisements.manage',
            'newsletters.view', 'newsletters.manage',
            'player_reviews.view', 'player_reviews.manage',
            'geo_blocking.view', 'geo_blocking.manage',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        $adminRoleId = DB::table('roles')->where('name', 'ADMIN')->where('guard_name', 'web')->value('id');
        if ($adminRoleId === null) {
            return;
        }

        foreach ($permissions as $permission) {
            $permissionId = DB::table('permissions')->where('name', $permission)->where('guard_name', 'web')->value('id');
            if ($permissionId !== null) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $adminRoleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Permissions may have been assigned to custom roles after release;
        // removing them during rollback would silently change authorization.
    }
};
