<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class CreateRecurringCompetitionAction
{
    public function __construct(
        private readonly CreateTournamentTemplateAction $createTemplate,
        private readonly GenerateRecurringTournamentAction $generateOccurrence,
    ) {}

    /**
     * Persist the recurring definition and materialize its first occurrence.
     * The definition stores relative windows so every future occurrence keeps
     * the same registration/check-in cadence as the schedule configured by admin.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $creator): Tournament
    {
        return DB::transaction(function () use ($data, $creator): Tournament {
            $startAt = CarbonImmutable::instance($data['start_at']);
            $frequency = RecurrenceFrequency::from((string) $data['frequency']);

            $template = $this->createTemplate->execute([
                'game_id' => $data['game_id'],
                'competition_type' => $data['competition_type'],
                'name' => $data['name'],
                'format' => 'single_elimination',
                'max_participants' => $data['max_participants'],
                'min_participants' => $data['min_participants'],
                'entry_fee' => $data['entry_fee'],
                'checkin_minutes' => CarbonImmutable::instance($data['checkin_open_at'])->diffInMinutes($startAt),
                'is_recurring' => true,
                'is_auto_cancel_underfilled' => $data['is_auto_cancel_underfilled'] ?? false,
                'recurrence_frequency' => $frequency,
                'timezone' => $data['timezone'],
                'next_run_at' => $startAt,
                // The first occurrence must be generated immediately. Future runs
                // retain the same advance horizon selected by this initial schedule.
                'generation_lead_minutes' => max(60, (int) now()->diffInMinutes($startAt)),
                'settings_json' => [
                    'registration_open_lead_minutes' => CarbonImmutable::instance($data['registration_open_at'])->diffInMinutes($startAt),
                    'registration_close_lead_minutes' => CarbonImmutable::instance($data['registration_close_at'])->diffInMinutes($startAt),
                    'checkin_open_lead_minutes' => CarbonImmutable::instance($data['checkin_open_at'])->diffInMinutes($startAt),
                    'checkin_close_lead_minutes' => CarbonImmutable::instance($data['checkin_close_at'])->diffInMinutes($startAt),
                    'day_of_month' => $startAt->setTimezone($data['timezone'])->day,
                    'description' => $data['description'] ?? null,
                    'rules' => $data['rules'] ?? null,
                    'platform_id' => $data['platform_id'] ?? null,
                    'prize_pool' => $data['prize_pool'] ?? 0,
                    'end_lead_minutes' => isset($data['end_at'])
                        ? $startAt->diffInMinutes(CarbonImmutable::instance($data['end_at']), false)
                        : 1440,
                    'registration_duration_minutes' => $data['registration_duration_minutes'] ?? 10,
                    'extra_registration_minutes' => $data['extra_registration_minutes'] ?? 0,
                    'team_size' => $data['team_size'] ?? 1,
                    'waiting_time' => $data['waiting_time'] ?? null,
                    'match_ready_minutes' => $data['match_ready_minutes'] ?? 10,
                    'match_extra_wait_minutes' => $data['match_extra_wait_minutes'] ?? 10,
                    'waiting_result_time' => $data['waiting_result_time'] ?? null,
                    'winning_points' => $data['winning_points'] ?? null,
                    'play_xp' => $data['play_xp'] ?? 100,
                    'winner_bonus_xp' => $data['winner_bonus_xp'] ?? 50,
                    'prize_1st' => $data['prize_1st'] ?? null,
                    'prize_2nd' => $data['prize_2nd'] ?? null,
                    'prize_3rd' => $data['prize_3rd'] ?? null,
                    'banner_url' => $data['banner_url'] ?? null,
                    'is_featured' => $data['is_featured'] ?? false,
                    'stream_urls' => [
                        'youtube' => $data['youtube_stream_url'] ?? null,
                        'twitch' => $data['twitch_stream_url'] ?? null,
                        'facebook' => $data['facebook_stream_url'] ?? null,
                    ],
                ],
            ]);

            $occurrence = $this->generateOccurrence->execute($template, $creator, $startAt->addMinute());

            if ($occurrence === null) {
                // Rolling back the template prevents an orphaned recurring
                // definition when its mandatory first occurrence cannot be made.
                throw new \LogicException('The first recurring competition occurrence could not be generated.');
            }

            return $occurrence;
        });
    }
}
