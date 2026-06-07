<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run core system seeders
        $this->call([
            RolesAndPermissionsSeeder::class,
            PlatformSystemUserSeeder::class,
            GamesTableSeeder::class,
            SystemSettingsSeeder::class,
        ]);

        // Seed a default test user
        $testUser = User::factory()->create([
            'email' => 'test@example.com',
            'username' => 'test_user',
        ]);

        if (! $testUser instanceof User) {
            throw new \RuntimeException('Failed to create test user.');
        }

        $testUserId = $testUser->getKey();

        // Seed profile for test user
        DB::table('user_profiles')->updateOrInsert(
            ['user_id' => $testUserId],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $testUserId,
                'display_name' => 'Test User',
                'country_code' => 'PH',
                'timezone' => 'Asia/Manila',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Ensure the test user has a wallet
        DB::table('wallets')->updateOrInsert(
            ['user_id' => $testUserId],
            [
                'uuid' => (string) Str::uuid(),
                'user_id' => $testUserId,
                'cached_balance' => 100.00, // Give them some starting balance for testing
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Assign PLAYER role to the test user
        $playerRole = DB::table('roles')->where('name', 'PLAYER')->first();
        if ($playerRole) {
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $playerRole->id,
                    'model_type' => 'App\Modules\Identity\Models\User',
                    'model_id' => $testUserId,
                ],
                [
                    'role_id' => $playerRole->id,
                    'model_type' => 'App\Modules\Identity\Models\User',
                    'model_id' => $testUserId,
                ]
            );
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $playerRole->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $testUserId,
                ],
                [
                    'role_id' => $playerRole->id,
                    'model_type' => 'App\Models\User',
                    'model_id' => $testUserId,
                ]
            );
        }
    }
}
