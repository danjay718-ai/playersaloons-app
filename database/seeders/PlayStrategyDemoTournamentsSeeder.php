<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlayStrategyDemoTournamentsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlayStrategyGamesSeeder::class);

        $creator = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['SUPER_ADMIN', 'ADMIN']))
            ->first()
            ?? User::query()->first();

        if ($creator === null) {
            throw new \RuntimeException('No user found to own demo tournaments.');
        }

        $webPlatform = Platform::query()->where('slug', 'web-browser')->firstOrFail();

        $events = [
            [
                'game_slug' => 'playstrategy-chess',
                'slug' => 'playstrategy-chess-live-demo-cup',
                'name' => 'Chess 1v1 Live Demo Cup',
                'description' => 'Instant 2-player PlayStrategy Chess match. Link your PlayStrategy account in Profile, join with 2 players, and play directly on an automated PlayStrategy board.',
                'max_participants' => 2,
                'min_participants' => 2,
                'entry_fee' => '0.00',
                'prize_pool' => '25.00',
                'hours_from_now' => 4,
            ],
            [
                'game_slug' => 'playstrategy-backgammon',
                'slug' => 'playstrategy-backgammon-arena-open',
                'name' => 'Backgammon Arena 1v1 Open',
                'description' => 'Automated PlayStrategy Backgammon tournament with live match link generation and API result verification.',
                'max_participants' => 2,
                'min_participants' => 2,
                'entry_fee' => '0.00',
                'prize_pool' => '20.00',
                'hours_from_now' => 6,
            ],
            [
                'game_slug' => 'playstrategy-draughts',
                'slug' => 'playstrategy-draughts-blitz-series',
                'name' => 'International Draughts Blitz Cup',
                'description' => '10x10 International Draughts knockout hosted on PlayStrategy with automated bracket progression.',
                'max_participants' => 4,
                'min_participants' => 2,
                'entry_fee' => '0.00',
                'prize_pool' => '40.00',
                'hours_from_now' => 8,
            ],
            [
                'game_slug' => 'playstrategy-flipello',
                'slug' => 'playstrategy-othello-showdown',
                'name' => 'Othello (Flipello) Showdown',
                'description' => 'Fast-paced 1v1 Othello (Flipello) match powered by PlayStrategy with verified winner sync.',
                'max_participants' => 2,
                'min_participants' => 2,
                'entry_fee' => '0.00',
                'prize_pool' => '15.00',
                'hours_from_now' => 10,
            ],
        ];

        foreach ($events as $index => $event) {
            $game = Game::query()->where('slug', $event['game_slug'])->first();
            if ($game === null) {
                continue;
            }

            $template = TournamentTemplate::query()->firstOrCreate(
                [
                    'game_id' => $game->id,
                    'name' => $event['name'],
                    'workflow_version' => 2,
                ],
                [
                    'uuid' => Str::uuid()->toString(),
                    'created_by' => $creator->id,
                    'competition_type' => CompetitionType::TOURNAMENT,
                    'format' => 'single_elimination',
                    'max_participants' => $event['max_participants'],
                    'min_participants' => $event['min_participants'],
                    'entry_fee' => $event['entry_fee'],
                    'prize_model' => 'v2_percentage',
                    'checkin_minutes' => 0,
                    'is_recurring' => true,
                    'recurrence_frequency' => 'daily',
                    'timezone' => 'Asia/Manila',
                ],
            );

            $slot = TournamentScheduleSlot::query()->firstOrCreate(
                [
                    'tournament_template_id' => $template->id,
                    'identity_key' => 'demo-slot-'.$index,
                ],
                [
                    'uuid' => Str::uuid()->toString(),
                    'local_start_time' => sprintf('%02d:00:00', (18 + $index) % 24),
                    'is_active' => true,
                    'sort_order' => $index,
                ],
            );

            $startAt = now()->addHours($event['hours_from_now']);

            Tournament::query()->updateOrCreate(
                ['slug' => $event['slug']],
                [
                    'uuid' => Str::uuid()->toString(),
                    'workflow_version' => 2,
                    'template_id' => $template->id,
                    'schedule_slot_id' => $slot->id,
                    'occurrence_period_key' => now()->format('Y-m-d').'-demo-'.$index,
                    'game_id' => $game->id,
                    'platform_id' => $webPlatform->id,
                    'platform_ids' => [$webPlatform->id],
                    'competition_type' => CompetitionType::TOURNAMENT,
                    'name' => $event['name'],
                    'description' => $event['description'],
                    'status' => TournamentStatus::REGISTRATION_OPEN,
                    'entry_fee' => $event['entry_fee'],
                    'prize_pool' => $event['prize_pool'],
                    'max_participants' => $event['max_participants'],
                    'min_participants' => $event['min_participants'],
                    'team_size' => 1,
                    'frequency' => 'daily',
                    'is_featured' => true,
                    'start_at' => $startAt,
                    'end_at' => $startAt->copy()->addHours(3),
                    'join_closes_at' => $startAt,
                    'created_by' => $creator->id,
                ],
            );
        }
    }
}
