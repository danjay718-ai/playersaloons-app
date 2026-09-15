<?php

declare(strict_types=1);

use App\Modules\Operations\Services\AdminDeletionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $tables = [
        'platforms', 'advertisements', 'newsletter_campaigns', 'contact_inquiries',
        'player_reviews', 'broadcast_messages', 'error_incidents', 'stream_channels',
        'tournament_schedule_slots', 'blocked_countries', 'translation_strings', 'roles', 'activity_log',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->softDeletes());
        }

        // A user may submit a new review after the previous review is deleted.
        // Retain the deleted review rather than overwriting its moderation history.
        Schema::table('player_reviews', function (Blueprint $blueprint): void {
            $blueprint->index('user_id');
            $blueprint->dropUnique(['user_id']);
        });

        $permissions = array_unique(array_column(AdminDeletionService::RESOURCES, 1));
        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore(['name' => $permission, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }
        $roleId = DB::table('roles')->where('name', 'SUPER_ADMIN')->where('guard_name', 'web')->value('id');
        if ($roleId !== null) {
            foreach (DB::table('permissions')->whereIn('name', $permissions)->where('guard_name', 'web')->pluck('id') as $id) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $id]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (DB::table($table)->whereNotNull('deleted_at')->exists()) {
                throw new RuntimeException('Cannot roll back recoverable deletion while deleted records are stored.');
            }
        }
        Schema::table('player_reviews', function (Blueprint $blueprint): void {
            $blueprint->unique('user_id');
            $blueprint->dropIndex(['user_id']);
        });
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropSoftDeletes());
        }
    }
};
