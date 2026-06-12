<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds two human-accessible staff accounts for local development / demo use.
 *
 * Accounts created:
 *   SUPER_ADMIN  →  admin@playersaloons.com  /  Admin@1234!
 *   ADMIN        →  staff@playersaloons.com  /  Staff@1234!
 *
 * ⚠️  NEVER run this seeder in production.  These credentials are
 *     intentionally visible in source control for local development only.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Staff accounts to create.
     *
     * @var array<int, array{email: string, username: string, password: string, role: string}>
     */
    private array $staffAccounts = [
        [
            'email'    => 'admin@playersaloons.com',
            'username' => 'superadmin',
            'password' => 'Admin@1234!',
            'role'     => 'SUPER_ADMIN',
        ],
        [
            'email'    => 'staff@playersaloons.com',
            'username' => 'staffadmin',
            'password' => 'Staff@1234!',
            'role'     => 'ADMIN',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->staffAccounts as $account) {
            // Upsert user
            $existing = DB::table('users')->where('email', $account['email'])->first();

            if ($existing) {
                $userId = $existing->id;
                // Ensure password is up-to-date (re-hash in case it changed)
                DB::table('users')->where('id', $userId)->update([
                    'password'   => Hash::make($account['password']),
                    'updated_at' => now(),
                ]);
            } else {
                $userId = DB::table('users')->insertGetId([
                    'uuid'              => (string) Str::uuid(),
                    'email'             => $account['email'],
                    'username'          => $account['username'],
                    'password'          => Hash::make($account['password']),
                    'email_verified_at' => now(),
                    'status'            => 'active',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            // Ensure wallet exists
            $walletExists = DB::table('wallets')->where('user_id', $userId)->exists();
            if (! $walletExists) {
                DB::table('wallets')->insert([
                    'uuid'           => (string) Str::uuid(),
                    'user_id'        => $userId,
                    'cached_balance' => 0.00,
                    'status'         => 'active',
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // Assign role
            $role = DB::table('roles')->where('name', $account['role'])->first();
            if ($role) {
                DB::table('model_has_roles')->updateOrInsert(
                    [
                        'role_id'    => $role->id,
                        'model_type' => 'App\Modules\Identity\Models\User',
                        'model_id'   => $userId,
                    ],
                    [
                        'role_id'    => $role->id,
                        'model_type' => 'App\Modules\Identity\Models\User',
                        'model_id'   => $userId,
                    ]
                );
            }

            $this->command->info("✅  [{$account['role']}] {$account['email']} seeded.");
        }
    }
}
