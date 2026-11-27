<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\GameCategory;
use App\Modules\CMS\Models\GameTranslation;
use App\Modules\CMS\Models\Platform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlayStrategyGamesSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(GameCategorySeeder::class);

        if (! config('features.playstrategy.enabled', true)) {
            return;
        }

        $cardBoardCategory = GameCategory::query()
            ->where('slug', GameCategory::CARD_BOARD_GAMES_SLUG)
            ->firstOrFail();

        $webPlatform = Platform::query()->firstOrCreate(
            ['slug' => 'web-browser'],
            ['name' => 'Web Browser', 'is_active' => true],
        );

        $games = [
            [
                'slug' => 'playstrategy-chess',
                'name' => 'Chess',
                'description' => 'Classic 1v1 competitive chess powered by PlayStrategy with automated match boards and verified results.',
                'variant' => 'standard',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => '/images/games/playstrategy-chess.svg',
            ],
            [
                'slug' => 'playstrategy-backgammon',
                'name' => 'Backgammon',
                'description' => 'Head-to-head competitive backgammon hosted on PlayStrategy with automated result tracking.',
                'variant' => 'backgammon',
                'clock_limit' => 420,
                'clock_increment' => 5,
                'artwork' => '/images/games/playstrategy-backgammon.svg',
            ],
            [
                'slug' => 'playstrategy-draughts',
                'name' => 'International Draughts',
                'description' => 'Competitive 10x10 International Draughts on PlayStrategy with live browser boards.',
                'variant' => 'international',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => '/images/games/playstrategy-draughts.svg',
            ],
            [
                'slug' => 'playstrategy-flipello',
                'name' => 'Othello (Flipello)',
                'description' => 'Fast-paced strategy board battles in Othello (Flipello) via PlayStrategy.',
                'variant' => 'flipello',
                'clock_limit' => 300,
                'clock_increment' => 3,
                'artwork' => '/images/games/playstrategy-flipello.svg',
            ],
        ];

        foreach ($games as $entry) {
            $game = Game::withTrashed()->where('slug', $entry['slug'])->first();

            if ($game === null) {
                $game = Game::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'slug' => $entry['slug'],
                    'game_category_id' => $cardBoardCategory->id,
                    'gameplay_provider' => Game::PROVIDER_PLAYSTRATEGY,
                    'provider_config' => [
                        'variant' => $entry['variant'],
                        'clock_limit' => $entry['clock_limit'],
                        'clock_increment' => $entry['clock_increment'],
                        'rated' => false,
                    ],
                    'card_image_path' => $entry['artwork'],
                    'banner_path' => $entry['artwork'],
                    'game_id_settings' => [
                        'default' => [
                            'label' => 'PlayStrategy Username',
                            'example' => 'Linked via PlayStrategy OAuth',
                            'instructions' => 'Connect your PlayStrategy account in your Profile before joining. Match links and results sync automatically.',
                            'connection_method' => 'playstrategy_oauth',
                        ],
                    ],
                    'is_active' => true,
                ]);
            } else {
                $updates = [];
                if (empty($game->card_image_path)) {
                    $updates['card_image_path'] = $entry['artwork'];
                }
                if (empty($game->banner_path)) {
                    $updates['banner_path'] = $entry['artwork'];
                }
                if ($updates !== []) {
                    $game->forceFill($updates)->save();
                }
            }

            if ($game->platforms()->count() === 0) {
                $game->platforms()->syncWithoutDetaching([$webPlatform->id]);
            }

            GameTranslation::query()->firstOrCreate(
                ['game_id' => $game->id, 'locale' => 'en'],
                ['name' => $entry['name'], 'description' => $entry['description']],
            );
        }
    }
}
