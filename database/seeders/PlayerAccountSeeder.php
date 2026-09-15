<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Models\UserProfile;
use App\Shared\Enums\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Modules\Identity\Models\Role;

class PlayerAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Demo seeding is available only in local or testing environments.');
        }

        // Ensure the PLAYER role exists
        $role = Role::firstOrCreate(['name' => 'PLAYER', 'guard_name' => 'web']);

        $passwordHash = Hash::make('Password123');
        $now = now();

        $chunkSize = 100;
        for ($start = 1; $start <= 1000; $start += $chunkSize) {
            $end = min($start + $chunkSize - 1, 1000);

            DB::transaction(function () use ($start, $end, $role, $passwordHash, $now): void {
                for ($i = $start; $i <= $end; $i++) {
                    $email = 'player'.$i.'@example.com';
                    $username = 'player'.$i;

                    $user = User::query()->firstOrNew([
                        'email' => $email,
                    ]);

                    if (! $user->exists) {
                        $user->uuid = (string) Str::uuid();
                        $user->email_verified_at = $now;
                        $user->status = UserStatus::ACTIVE;
                    }

                    $user->password = $passwordHash;
                    $user->username = $username;
                    $user->save();

                    // Ensure user profile exists
                    $profile = UserProfile::query()->firstOrNew(['user_id' => $user->id]);
                    if (! $profile->exists) {
                        $profile->uuid = (string) Str::uuid();
                        $profile->display_name = 'Player '.$i;
                        $profile->country_code = 'US';
                        $profile->save();
                    }

                    // Ensure wallet exists
                    $walletExists = DB::table('wallets')->where('user_id', $user->id)->exists();
                    if (! $walletExists) {
                        DB::table('wallets')->insert([
                            'uuid' => (string) Str::uuid(),
                            'user_id' => $user->id,
                            'cached_balance' => 0.00,
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    // Assign PLAYER role
                    DB::table('model_has_roles')->updateOrInsert(
                        [
                            'role_id' => $role->id,
                            'model_type' => User::class,
                            'model_id' => $user->id,
                        ],
                        [
                            'role_id' => $role->id,
                            'model_type' => User::class,
                            'model_id' => $user->id,
                        ]
                    );
                }
            });
        }
    }
}
