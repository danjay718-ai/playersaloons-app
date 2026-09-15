<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LogicException;

final class AdminUserSeeder extends Seeder
{
    public static function accounts(): array
    {
        $development = app()->environment(['local', 'testing']);
        $accounts = [];
        foreach ([
            'super_admin' => ['role' => 'SUPER_ADMIN', 'email' => 'admin@playersaloons.com', 'password' => 'Admin@1234!'],
            'admin' => ['role' => 'ADMIN', 'email' => 'staff@playersaloons.com', 'password' => 'Staff@1234!'],
        ] as $key => $defaults) {
            $account = config('bootstrap.'.$key, []);
            $accounts[] = [
                'role' => $defaults['role'],
                'email' => $account['email'] ?? ($development ? $defaults['email'] : null),
                'username' => $account['username'] ?? $key,
                'password' => $account['password'] ?? ($development ? $defaults['password'] : null),
            ];
        }

        Validator::make(['accounts' => $accounts], [
            'accounts.*.email' => 'required|email|max:255|distinct|not_in:platform@playersaloons.com',
            'accounts.*.username' => 'required|string|max:255|alpha_dash|distinct|not_in:platform_system_user',
            'accounts.*.password' => $development ? 'required|string|min:8' : 'required|string|min:12|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[^a-zA-Z0-9]/|not_in:Admin@1234!,Staff@1234!',
        ], [
            'accounts.*.email.required' => 'Configure both BOOTSTRAP_SUPER_ADMIN_EMAIL and BOOTSTRAP_ADMIN_EMAIL before seeding.',
            'accounts.*.password.required' => 'Configure both bootstrap passwords through deployment secrets before seeding.',
        ])->validate();

        return $accounts;
    }

    public function run(): void
    {
        $accounts = self::accounts();
        DB::transaction(function () use ($accounts): void {
            foreach ($accounts as $account) {
                $existing = DB::table('users')->where('email', $account['email'])->first();
                if ($existing && $existing->deleted_at !== null) {
                    throw new LogicException('A bootstrap staff account is deleted. Restore it explicitly before seeding.');
                }
                $userId = $existing?->id ?? DB::table('users')->insertGetId([
                    'uuid' => (string) Str::uuid(), 'email' => $account['email'],
                    'username' => $account['username'], 'password' => Hash::make($account['password']),
                    'email_verified_at' => now(), 'status' => 'active',
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                // Reseeding does not reset existing passwords, profiles or balances.
                if (! DB::table('user_profiles')->where('user_id', $userId)->exists()) {
                    DB::table('user_profiles')->insert([
                        'uuid' => (string) Str::uuid(), 'user_id' => $userId,
                        'display_name' => $account['role'] === 'SUPER_ADMIN' ? 'Super Admin' : 'Admin',
                        'timezone' => config('app.tournament_timezone', 'UTC'),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                if (! DB::table('wallets')->where('user_id', $userId)->exists()) {
                    DB::table('wallets')->insert([
                        'uuid' => (string) Str::uuid(), 'user_id' => $userId,
                        'cached_balance' => '0.00', 'status' => 'active',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $roleId = DB::table('roles')->where('name', $account['role'])->where('guard_name', 'web')->whereNull('deleted_at')->value('id');
                if ($roleId === null) {
                    throw new LogicException('Seed system roles before bootstrap staff accounts.');
                }
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $roleId, 'model_type' => User::class,
                    'model_id' => $userId,
                ]);
                $this->command?->info('['.$account['role'].'] staff account ready.');
            }
        });
    }
}
