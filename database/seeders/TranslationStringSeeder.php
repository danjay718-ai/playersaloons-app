<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Localization\Services\TranslationCatalogService;
use Illuminate\Database\Seeder;

final class TranslationStringSeeder extends Seeder
{
    public function run(): void
    {
        app(TranslationCatalogService::class)->syncFromJsonFiles();
    }
}
