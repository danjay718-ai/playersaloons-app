<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Modules\Identity\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * All permissions grouped by resource.
     * Naming convention: {resource}.{action}
     *
     * @var array<int, string>
     */
    private array $permissions = [
        // Recoverable management deletion
        'games.delete', 'games.restore', 'games.force_delete', 'platforms.delete', 'tournaments.delete', 'policies.delete',
        'cms.delete', 'navigation.delete', 'advertisements.delete', 'broadcast_messages.delete',
        'contact_inquiries.delete', 'newsletters.delete', 'player_reviews.delete',
        'error_incidents.delete', 'streams.delete', 'roles.delete', 'translations.delete', 'geo_blocking.delete',

        // Users
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.reset_password',
        'users.suspend',
        'users.unsuspend',
        'users.assign_role',
        'users.revoke_role',

        // KYC
        'kyc.view',
        'kyc.review',
        'kyc.approve',
        'kyc.reject',

        // Tournaments
        'tournaments.view',
        'tournaments.create',
        'tournaments.publish',
        'tournaments.manage',
        'tournaments.cancel',
        'tournaments.register',

        // Matches
        'matches.view',
        'matches.manage',
        'matches.submit_result',

        // Disputes
        'disputes.open',
        'disputes.view',
        'disputes.resolve',

        // Teams
        'teams.view',
        'teams.create',
        'teams.manage',
        'teams.invite',
        'teams.remove_member',

        // Wallets
        'wallets.view',
        'wallets.request_withdrawal',
        'wallets.suspend',
        'wallets.unsuspend',
        'wallets.freeze',
        'wallets.unfreeze',

        // Withdrawals
        'withdrawals.view',
        'withdrawals.review',
        'withdrawals.approve',
        'withdrawals.reject',

        // Deposits
        'deposits.view',

        // System / Operations
        'system_settings.view',
        'system_settings.manage',
        'audit_logs.view',
        'audit_logs.manage',
        'error_incidents.view',
        'error_incidents.manage',
        'broadcast_messages.manage',
        'contact_inquiries.manage',
        'staff_activity.view',
        'roles.view',
        'roles.manage',
        'translations.view',
        'translations.manage',
        'advertisements.view',
        'advertisements.manage',
        'newsletters.view',
        'newsletters.manage',
        'player_reviews.view',
        'player_reviews.manage',
        'geo_blocking.view',
        'geo_blocking.manage',

        // CMS
        'cms.view',
        'cms.manage',
        'games.view',
        'games.manage',
    ];

    /**
     * Role → permission assignments.
     *
     * @var array<string, array<int, string>>
     */
    private array $rolePermissions = [
        'PLAYER' => [
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
        ],

        'TEAM_CAPTAIN' => [
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
        ],

        'TOURNAMENT_ORGANIZER' => [
            'tournaments.view',
            'tournaments.create',
            'tournaments.publish',
            'tournaments.manage',
            'tournaments.cancel',
            'matches.view',
            'matches.manage',
            'disputes.view',
            'disputes.resolve',
            'teams.view',
            'wallets.view',
            'cms.view',
            'games.view',
        ],

        'MODERATOR' => [
            'users.view',
            'users.suspend',
            'users.unsuspend',
            'tournaments.view',
            'matches.view',
            'disputes.view',
            'teams.view',
            'wallets.view',
            'cms.view',
            'cms.manage',
            'games.view',
            'translations.view',
            'translations.manage',
            'broadcast_messages.manage',
            'audit_logs.view',
            'contact_inquiries.manage',
        ],

        'SUPPORT_AGENT' => [
            'users.view',
            'kyc.view',
            'tournaments.view',
            'matches.view',
            'disputes.view',
            'teams.view',
            'wallets.view',
            'withdrawals.view',
            'deposits.view',
            'cms.view',
            'games.view',
            'audit_logs.view',
            'contact_inquiries.manage',
        ],

        'FINANCE_OPERATOR' => [
            'users.view',
            'wallets.view',
            'wallets.suspend',
            'wallets.unsuspend',
            'withdrawals.view',
            'withdrawals.review',
            'withdrawals.approve',
            'withdrawals.reject',
            'deposits.view',
            'audit_logs.view',
        ],

        'KYC_REVIEWER' => [
            'users.view',
            'kyc.view',
            'kyc.review',
            'kyc.approve',
            'kyc.reject',
            'audit_logs.view',
        ],

        'ADMIN' => [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.reset_password',
            'users.suspend',
            'users.unsuspend',
            'users.assign_role',
            'users.revoke_role',
            // KYC
            'kyc.view',
            'kyc.review',
            'kyc.approve',
            'kyc.reject',
            // Tournaments
            'tournaments.view',
            'tournaments.create',
            'tournaments.publish',
            'tournaments.manage',
            'tournaments.cancel',
            // Matches
            'matches.view',
            'matches.manage',
            // Disputes
            'disputes.view',
            'disputes.resolve',
            // Teams
            'teams.view',
            'teams.manage',
            // Wallets
            'wallets.view',
            'wallets.suspend',
            'wallets.unsuspend',
            'wallets.freeze',
            // Withdrawals
            'withdrawals.view',
            'withdrawals.review',
            'withdrawals.approve',
            'withdrawals.reject',
            // Deposits
            'deposits.view',
            // System
            'system_settings.view',
            'system_settings.manage',
            'audit_logs.view',
            'audit_logs.manage',
            'error_incidents.view',
            'error_incidents.manage',
            'broadcast_messages.manage',
            'contact_inquiries.manage',
            'staff_activity.view',
            'roles.view',
            'roles.manage',
            'translations.view',
            'translations.manage',
            'advertisements.view',
            'advertisements.manage',
            'newsletters.view',
            'newsletters.manage',
            'player_reviews.view',
            'player_reviews.manage',
            'geo_blocking.view',
            'geo_blocking.manage',
            // CMS
            'cms.view',
            'cms.manage',
            'games.view',
            'games.manage',
        ],

        'SUPER_ADMIN' => [], // Gets all permissions via wildcard below
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        foreach ($this->permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach ($this->rolePermissions as $roleName => $permissions) {
            /** @var Role $role */
            $role = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($roleName === 'SUPER_ADMIN') {
                // SUPER_ADMIN gets every permission
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($permissions);
            }
        }

        $this->command->info('Roles and permissions seeded successfully.');
    }
}
