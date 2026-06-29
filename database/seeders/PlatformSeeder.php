<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CMS\Models\Platform;
use Illuminate\Database\Seeder;

final class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->platforms() as $platform) {
            Platform::query()->updateOrCreate(
                ['slug' => $platform['slug']],
                $platform
            );
        }
    }

    /**
     * @return array<int, array{name: string, slug: string, is_active: bool}>
     */
    private function platforms(): array
    {
        return [
            ['name' => 'PC', 'slug' => 'pc', 'is_active' => true],
            ['name' => 'Console', 'slug' => 'console', 'is_active' => true],
            ['name' => 'Mobile', 'slug' => 'mobile', 'is_active' => true],
            ['name' => 'Cross-Platform', 'slug' => 'cross-platform', 'is_active' => true],
        ];
    }
}
