<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Events\TournamentCreated;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTournamentAction
{
    /**
     * Create a new tournament in DRAFT status.
     *
     * @param  array{
     *     name: string,
     *     game_id: int,
     *     max_participants: int,
     *     min_participants: int,
     *     entry_fee?: string|float,
     *     prize_pool?: string|float,
     *     registration_open_at?: string|\DateTimeInterface|null,
     *     registration_close_at?: string|\DateTimeInterface|null,
     *     checkin_open_at?: string|\DateTimeInterface|null,
     *     checkin_close_at?: string|\DateTimeInterface|null,
     *     start_at?: string|\DateTimeInterface|null,
     *     template_id?: int|null,
     * } $data
     */
    public function execute(array $data, User $creator): Tournament
    {
        return DB::transaction(function () use ($data, $creator): Tournament {
            $tournament = new Tournament;
            $tournament->fill([
                'uuid' => Str::uuid()->toString(),
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::random(6),
                'game_id' => $data['game_id'],
                'competition_type' => $data['competition_type'] ?? CompetitionType::TOURNAMENT,
                'status' => TournamentStatus::DRAFT,
                'max_participants' => $data['max_participants'],
                'min_participants' => $data['min_participants'],
                'entry_fee' => $data['entry_fee'] ?? '0.00',
                'prize_pool' => $data['prize_pool'] ?? '0.00',
                'registration_open_at' => $data['registration_open_at'] ?? null,
                'registration_close_at' => $data['registration_close_at'] ?? null,
                'checkin_open_at' => $data['checkin_open_at'] ?? null,
                'checkin_close_at' => $data['checkin_close_at'] ?? null,
                'start_at' => $data['start_at'] ?? null,
                'end_at' => $data['end_at'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'description' => $data['description'] ?? null,
                'rules' => $data['rules'] ?? null,
                'platform_id' => $data['platform_id'] ?? null,
                'frequency' => $data['frequency'] ?? 'daily',
                'timezone' => $data['timezone'] ?? config('app.tournament_timezone', 'UTC'),
                'registration_duration_minutes' => $data['registration_duration_minutes'] ?? 10,
                'extra_registration_minutes' => $data['extra_registration_minutes'] ?? 0,
                'waiting_time' => $data['waiting_time'] ?? null,
                'match_ready_minutes' => $data['match_ready_minutes'] ?? 10,
                'match_extra_wait_minutes' => $data['match_extra_wait_minutes'] ?? 10,
                'waiting_result_time' => $data['waiting_result_time'] ?? null,
                'team_size' => $data['team_size'] ?? 1,
                'prize_1st' => filled($data['prize_1st'] ?? null) ? $data['prize_1st'] : null,
                'prize_2nd' => filled($data['prize_2nd'] ?? null) ? $data['prize_2nd'] : null,
                'prize_3rd' => filled($data['prize_3rd'] ?? null) ? $data['prize_3rd'] : null,
                'winning_points' => $data['winning_points'] ?? null,
                'play_xp' => $data['play_xp'] ?? 100,
                'winner_bonus_xp' => $data['winner_bonus_xp'] ?? ($data['winning_points'] ?? 50),
                'is_auto_cancel_underfilled' => $data['is_auto_cancel_underfilled'] ?? false,
                'banner_url' => $data['banner_url'] ?? null,
                'is_featured' => $data['is_featured'] ?? false,
                'created_by' => $creator->getKey(),
            ]);

            if ($tournament->competition_type === CompetitionType::HEAD_TO_HEAD) {
                // Platform H2H is represented by the standard competition
                // lifecycle, but its participant shape is an invariant.
                $tournament->min_participants = 2;
                $tournament->max_participants = 2;
                $tournament->team_size = 1;
            }
            $tournament->save();

            TournamentCreated::dispatch((int) $tournament->getKey(), (int) $creator->getKey());

            return $tournament;
        });
    }
}
