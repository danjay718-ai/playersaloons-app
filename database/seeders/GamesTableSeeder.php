<?php

namespace Database\Seeders;

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
        $games = [
            [
                'slug' => 'mobile-legends-bang-bang',
                'name' => 'Mobile Legends: Bang Bang',
                'description' => 'A popular multiplayer online battle arena (MOBA) game designed for mobile phones.',
            ],
            [
                'slug' => 'pubg-mobile',
                'name' => 'PUBG Mobile',
                'description' => 'A battle royale shooter game where players fight for survival on a shrinking island.',
            ],
            [
                'slug' => 'call-of-duty-mobile',
                'name' => 'Call of Duty: Mobile',
                'description' => 'A free-to-play shooter game delivering signature multiplayer and battle royale action.',
            ],
            [
                'slug' => 'dota-2',
                'name' => 'Dota 2',
                'description' => 'A deep, strategic multiplayer online battle arena game developed by Valve.',
            ],
            [
                'slug' => 'valorant',
                'name' => 'Valorant',
                'description' => 'A 5v5 character-based tactical shooter game developed by Riot Games.',
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
