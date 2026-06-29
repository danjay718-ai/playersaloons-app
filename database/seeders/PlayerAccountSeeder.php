<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class PlayerAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure the PLAYER role exists
        $role = Role::firstOrCreate(['name' => 'PLAYER', 'guard_name' => 'web']);
        
        $password = Hash::make('password123');

        for ($i = 1; $i <= 50; $i++) {
            $user = User::query()->firstOrNew([
                'email' => 'player'.$i.'@example.com',
            ]);

            if (! $user->exists) {
                $user->uuid = (string) Str::uuid();
                $user->password = $password;
                $user->email_verified_at = now();
            }

            $user->username = 'testplayer'.$i;
            $user->save();

            $user->assignRole($role);
        }
    }
}
