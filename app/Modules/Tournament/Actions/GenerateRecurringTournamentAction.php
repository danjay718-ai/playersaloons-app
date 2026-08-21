<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Stream\Actions\SyncTournamentStreamChannelsAction;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTemplate;
use App\Modules\Tournament\Services\RecurrenceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class GenerateRecurringTournamentAction
{
    public function __construct(
        private readonly CreateTournamentAction $createTournament,
        private readonly PublishTournamentAction $publishTournament,
        private readonly RecurrenceSchedule $schedule,
        private readonly SyncTournamentStreamChannelsAction $syncStreams,
    ) {}

    /**
     * Generate at most one due occurrence and atomically advance the template.
     *
     * Locking the template plus the database unique key on template/start time
     * makes this safe across overlapping scheduler processes and deployments.
     */
    public function execute(TournamentTemplate $template, User $creator, CarbonImmutable $horizon): ?Tournament
    {
        return DB::transaction(function () use ($template, $creator, $horizon): ?Tournament {
            /** @var TournamentTemplate $locked */
            $locked = TournamentTemplate::query()->lockForUpdate()->findOrFail($template->getKey());

            if (! $locked->is_recurring || $locked->recurrence_frequency === null || $locked->next_run_at === null) {
                return null;
            }

            $settings = $locked->settings_json ?? [];
            $nextRun = CarbonImmutable::instance($locked->next_run_at);

            // Do not backfill already-missed events after downtime. Advancing to
            // the next future slot prevents stale competitions from flooding UI.
            while ($nextRun->lessThanOrEqualTo(now())) {
                $nextRun = $this->schedule->next(
                    $nextRun,
                    $locked->recurrence_frequency,
                    $locked->timezone,
                    $settings,
                )->utc();
            }

            if ($nextRun->greaterThan($horizon)) {
                if (! $nextRun->equalTo($locked->next_run_at)) {
                    $locked->next_run_at = $nextRun;
                    $locked->save();
                }

                return null;
            }

            $existing = Tournament::query()
                ->where('template_id', $locked->getKey())
                ->where('start_at', $nextRun)
                ->first();

            if ($existing === null) {
                $tournament = $this->createTournament->execute(
                    $this->occurrenceData($locked, $nextRun, $settings),
                    $creator,
                );
                $tournament = $this->publishTournament->execute($tournament);
                $this->syncStreams->execute(
                    $tournament,
                    is_array($settings['stream_urls'] ?? null) ? $settings['stream_urls'] : [],
                    $creator,
                );
            } else {
                $tournament = $existing;
            }

            $locked->last_generated_at = now();
            $locked->next_run_at = $this->schedule->next(
                $nextRun,
                $locked->recurrence_frequency,
                $locked->timezone,
                $settings,
            )->utc();
            $locked->save();

            return $tournament;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function occurrenceData(
        TournamentTemplate $template,
        CarbonImmutable $startAt,
        array $settings,
    ): array {
        $registrationOpenLead = max(1, (int) ($settings['registration_open_lead_minutes'] ?? 1440));
        $registrationCloseLead = max(1, (int) ($settings['registration_close_lead_minutes'] ?? 30));
        $checkinOpenLead = max(1, (int) ($settings['checkin_open_lead_minutes'] ?? $template->checkin_minutes));
        $checkinCloseLead = max(0, (int) ($settings['checkin_close_lead_minutes'] ?? 1));

        return [
            'name' => $template->name.' - '.$startAt->setTimezone($template->timezone)->format('M d, Y H:i'),
            'game_id' => $template->game_id,
            'competition_type' => $template->competition_type,
            'max_participants' => $template->max_participants,
            'min_participants' => $template->min_participants,
            'entry_fee' => $template->entry_fee,
            'prize_pool' => $settings['prize_pool'] ?? 0,
            'registration_open_at' => $startAt->subMinutes($registrationOpenLead),
            'registration_close_at' => $startAt->subMinutes($registrationCloseLead),
            'checkin_open_at' => $startAt->subMinutes($checkinOpenLead),
            'checkin_close_at' => $startAt->subMinutes($checkinCloseLead),
            'start_at' => $startAt,
            'end_at' => $startAt->addMinutes(max(1, (int) ($settings['end_lead_minutes'] ?? 1440))),
            'template_id' => $template->getKey(),
            'description' => $settings['description'] ?? null,
            'rules' => $settings['rules'] ?? null,
            'platform_id' => $settings['platform_id'] ?? null,
            'frequency' => $template->recurrence_frequency->value,
            'timezone' => $template->timezone,
            'registration_duration_minutes' => $settings['registration_duration_minutes'] ?? 10,
            'extra_registration_minutes' => $settings['extra_registration_minutes'] ?? 0,
            'team_size' => $settings['team_size'] ?? 1,
            'waiting_time' => $settings['waiting_time'] ?? null,
            'match_ready_minutes' => $settings['match_ready_minutes'] ?? 10,
            'match_extra_wait_minutes' => $settings['match_extra_wait_minutes'] ?? 10,
            'waiting_result_time' => $settings['waiting_result_time'] ?? null,
            'winning_points' => $settings['winning_points'] ?? null,
            'play_xp' => $settings['play_xp'] ?? 100,
            'winner_bonus_xp' => $settings['winner_bonus_xp'] ?? 50,
            'prize_1st' => $settings['prize_1st'] ?? null,
            'prize_2nd' => $settings['prize_2nd'] ?? null,
            'prize_3rd' => $settings['prize_3rd'] ?? null,
            'is_auto_cancel_underfilled' => $template->is_auto_cancel_underfilled,
            'banner_url' => $settings['banner_url'] ?? null,
            'is_featured' => $settings['is_featured'] ?? false,
        ];
    }
}
