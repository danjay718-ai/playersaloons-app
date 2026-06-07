<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformSystemUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if system user already exists
        $systemUser = DB::table('users')->where('email', 'platform@playersaloons.com')->first();

        if ($systemUser) {
            $userId = $systemUser->id;
        } else {
            $userId = DB::table('users')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'email' => 'platform@playersaloons.com',
                'username' => 'platform_system_user',
                'password' => Hash::make(Str::random(32)), // Random secure password
                'email_verified_at' => now(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Check if system wallet already exists for this user
        $systemWallet = DB::table('wallets')->where('user_id', $userId)->first();

        if (! $systemWallet) {
            DB::table('wallets')->insert([
                'uuid' => (string) Str::uuid(),
                'user_id' => $userId,
                'cached_balance' => 0.00,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Assign SUPER_ADMIN role to this user
        $superAdminRole = DB::table('roles')->where('name', 'SUPER_ADMIN')->first();

        if ($superAdminRole) {
            // Assign for App\Modules\Identity\Models\User
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $superAdminRole->id,
                    'model_type' => 'App\Modules\Identity\Models\User',
                    'model_id' => $userId,
                ],
                [
                    'role_id' => $superAdminRole->id,
                    'model_type' => 'App\Modules\Identity\Models\User',
                    'model_id' => $userId,
                ]
            );

            // Also assign for App\Models\User just in case it's used before the namespace move
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $superAdminRole->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $userId,
                ],
                [
                    'role_id' => $superAdminRole->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $userId,
                ]
            );
        }
    }
}
