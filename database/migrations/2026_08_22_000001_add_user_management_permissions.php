<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var array<int, string> */
    private const USER_MANAGEMENT_PERMISSIONS = [
        'users.create',
        'users.update',
        'users.delete',
        'users.reset_password',
    ];

    /** @var array<int, string> */
    private const PLAYER_PERMISSIONS = [
        'tournaments.view', 'tournaments.register', 'matches.view', 'matches.submit_result',
        'disputes.open', 'teams.view', 'teams.create', 'teams.manage', 'teams.invite',
        'teams.remove_member', 'wallets.view', 'wallets.request_withdrawal', 'cms.view', 'games.view',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::USER_MANAGEMENT_PERMISSIONS as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::query()->where('name', 'ADMIN')->first()?->givePermissionTo(self::USER_MANAGEMENT_PERMISSIONS);
        Role::query()->where('name', 'PLAYER')->first()?->syncPermissions(self::PLAYER_PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::query()->whereIn('name', self::USER_MANAGEMENT_PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
