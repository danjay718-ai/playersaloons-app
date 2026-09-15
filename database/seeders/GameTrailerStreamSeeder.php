<?php

namespace Database\Seeders;

use App\Modules\CMS\Models\Game;
use App\Modules\Stream\Models\StreamChannel;
use Illuminate\Database\Seeder;

class GameTrailerStreamSeeder extends Seeder
{
    /**
     * Seed sample YouTube trailer embeds for the default game catalog.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException('Demo seeding is available only in local or testing environments.');
        }

        $trailers = [
            'mobile-legends-bang-bang' => [
                'title' => 'Mobile Legends: Bang Bang Sample Trailer',
                'source_url' => 'https://www.youtube.com/watch?v=1xaD3NuJ8T4',
            ],
            'pubg-mobile' => [
                'title' => 'PUBG Mobile Official Trailer',
                'source_url' => 'https://www.youtube.com/watch?v=uCd6tbUAy6o',
            ],
            'call-of-duty-mobile' => [
                'title' => 'Call of Duty: Mobile Official Launch Trailer',
                'source_url' => 'https://www.youtube.com/watch?v=0hH7a5TnF7I',
            ],
            'dota-2' => [
                'title' => 'Dota 2 Gamescom Trailer',
                'source_url' => 'https://www.youtube.com/watch?v=-cSFPIwMEq4',
            ],
            'valorant' => [
                'title' => 'VALORANT Official Launch Cinematic Trailer',
                'source_url' => 'https://www.youtube.com/watch?v=e_E9W2vsRbQ',
            ],
        ];

        foreach ($trailers as $gameSlug => $trailer) {
            $game = Game::query()->where('slug', $gameSlug)->first();

            if (! $game) {
                continue;
            }

            StreamChannel::query()->updateOrCreate(
                [
                    'game_id' => $game->getKey(),
                    'provider' => 'youtube',
                ],
                [
                    'user_id' => null,
                    'tournament_id' => null,
                    'source_url' => $trailer['source_url'],
                    'title' => $trailer['title'],
                    'is_public' => true,
                    'metadata' => [
                        'kind' => 'sample_game_trailer',
                        'seeded_by' => self::class,
                    ],
                ]
            );
        }
    }
}
