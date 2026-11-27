<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CMS\Models\GameCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GameCategorySeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'slug' => GameCategory::VIDEO_GAMES_SLUG,
                'name' => 'Video Games',
                'display_order' => 1,
            ],
            [
                'slug' => GameCategory::CARD_BOARD_GAMES_SLUG,
                'name' => 'Card & Board Games',
                'display_order' => 2,
            ],
        ];

        foreach ($defaults as $category) {
            GameCategory::query()->firstOrCreate(
                ['slug' => $category['slug']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'name' => $category['name'],
                    'display_order' => $category['display_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
