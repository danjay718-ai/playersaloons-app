<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class FreshInstallationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Validate staff secrets before any bootstrap records are written.
        AdminUserSeeder::accounts();
        DB::transaction(function (): void {
            $this->call([
                RolesAndPermissionsSeeder::class,
                PlatformSystemUserSeeder::class,
                AdminUserSeeder::class,
                PolicyPageSeeder::class,
                LandingPageSeeder::class,
                PublicNavigationSeeder::class,
                SystemSettingsSeeder::class,
            ]);
        });
    }
}
