<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\GameCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GamesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Demo seeding is available only in local or testing environments.');
        }

        $this->call(GameCategorySeeder::class);

        $videoGamesCategoryId = GameCategory::query()
            ->where('slug', GameCategory::VIDEO_GAMES_SLUG)
            ->value('id');

        if ($videoGamesCategoryId === null) {
            throw new \RuntimeException('Default Video Games category is missing.');
        }

        $games = [
            [
                'slug' => 'mobile-legends-bang-bang',
                'name' => 'Mobile Legends: Bang Bang',
                'description' => 'A popular multiplayer online battle arena (MOBA) game designed for mobile phones.',
                'category_id' => $videoGamesCategoryId,
            ],
            [
                'slug' => 'pubg-mobile',
                'name' => 'PUBG Mobile',
                'description' => 'A battle royale shooter game where players fight for survival on a shrinking island.',
                'category_id' => $videoGamesCategoryId,
            ],
            [
                'slug' => 'call-of-duty-mobile',
                'name' => 'Call of Duty: Mobile',
                'description' => 'A free-to-play shooter game delivering signature multiplayer and battle royale action.',
                'category_id' => $videoGamesCategoryId,
            ],
            [
                'slug' => 'dota-2',
                'name' => 'Dota 2',
                'description' => 'A deep, strategic multiplayer online battle arena game developed by Valve.',
                'category_id' => $videoGamesCategoryId,
            ],
            [
                'slug' => 'valorant',
                'name' => 'Valorant',
                'description' => 'A 5v5 character-based tactical shooter game developed by Riot Games.',
                'category_id' => $videoGamesCategoryId,
            ],
        ];

        foreach ($games as $gameData) {
            // Check if game already exists by slug
            $existingGame = DB::table('games')->where('slug', $gameData['slug'])->first();

            if ($existingGame) {
                $gameId = $existingGame->id;
            } else {
                $gameId = DB::table('games')->insertGetId([
                    'uuid' => (string) Str::uuid(),
                    'slug' => $gameData['slug'],
                    'game_category_id' => $gameData['category_id'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Insert translation
            DB::table('game_translations')->updateOrInsert(
                [
                    'game_id' => $gameId,
                    'locale' => 'en',
                ],
                [
                    'name' => $gameData['name'],
                    'description' => $gameData['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
