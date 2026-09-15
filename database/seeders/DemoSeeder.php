<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Demo seeding is available only in local or testing environments.');
        }

        $this->call([
            FreshInstallationSeeder::class,
            PlatformSeeder::class,
            GamesTableSeeder::class,
            GameTrailerStreamSeeder::class,
            TranslationStringSeeder::class,
            TournamentsTableSeeder::class,
            PlayerAccountSeeder::class,
        ]);
    }
}
