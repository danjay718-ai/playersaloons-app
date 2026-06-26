<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
            AdminUserSeeder::class,
            GamesTableSeeder::class,
            LandingPageSeeder::class,
            PublicNavigationSeeder::class,
            SystemSettingsSeeder::class,
            TournamentsTableSeeder::class,
            PlayerAccountSeeder::class,
        ]);
    }
}
