<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentScheduleSlot;
use App\Modules\Tournament\Services\OccurrencePeriod;
use App\Shared\Enums\CompetitionType;
use App\Shared\Enums\RecurrenceFrequency;
use App\Shared\Enums\TournamentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class MaterializeV2OccurrenceAction
{
    public function __construct(private readonly OccurrencePeriod $periods) {}

    public function execute(TournamentScheduleSlot $slot, User $creator, ?CarbonImmutable $now = null): ?Tournament
    {
        return DB::transaction(function () use ($slot, $creator, $now): ?Tournament {
            $lockedSlot = TournamentScheduleSlot::query()
                ->with('template.game.tournamentDefaults', 'template.game.headToHeadDefaults')
                ->lockForUpdate()
                ->findOrFail($slot->id);
            $template = $lockedSlot->template;
            if ((int) $template->workflow_version !== 2 || ! $lockedSlot->is_active) {
                throw new LogicException('This V2 schedule slot is not active.');
            }

            $localNow = ($now ?? CarbonImmutable::now($template->timezone))->setTimezone($template->timezone);
            $frequency = $template->recurrence_frequency;
            $isOneTime = ! $template->is_recurring;
            if (! $isOneTime && ! $frequency instanceof RecurrenceFrequency) {
                throw new LogicException('A recurring frequency is required.');
            }

            if ($isOneTime) {
                if ($lockedSlot->schedule_start_at === null || $lockedSlot->schedule_end_at === null) {
                    throw new LogicException('A one-time schedule requires start and end dates.');
                }
                $period = ['key' => 'once-'.$lockedSlot->id, 'start' => null, 'end' => null];
                $startLocal = CarbonImmutable::instance($lockedSlot->schedule_start_at)->setTimezone($template->timezone);
                $endLocal = CarbonImmutable::instance($lockedSlot->schedule_end_at)->setTimezone($template->timezone);
            } else {
                $period = $this->periods->current($frequency, $template->timezone, $now);
                $startLocal = $this->scheduledStart($lockedSlot, $frequency, $period['start']);
                $anchorStart = $lockedSlot->schedule_start_at === null
                    ? null
                    : CarbonImmutable::instance($lockedSlot->schedule_start_at)->setTimezone($template->timezone);

                // Materialize only the current recurrence period. A schedule
                // created after an elapsed slot waits until that next period
                // actually begins; it never pre-creates tomorrow's occurrence.
                if (($anchorStart !== null && $startLocal->lessThan($anchorStart)) || $startLocal->lessThan($localNow)) {
                    return null;
                }
                $endLocal = $this->scheduledEnd($lockedSlot, $startLocal, $period['end'], $template->timezone);
            }
            $existing = Tournament::query()
                ->where('schedule_slot_id', $lockedSlot->id)
                ->where('occurrence_period_key', $period['key'])
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $settings = array_replace($template->settings_json ?? [], $lockedSlot->overrides_json ?? []);
            // Tournament and platform H2H defaults deliberately remain
            // separate. The occurrence receives a snapshot either way.
            $defaults = $template->competition_type === CompetitionType::HEAD_TO_HEAD
                ? $template->game->headToHeadDefaults
                : $template->game->tournamentDefaults;

            return Tournament::query()->create([
                'uuid' => Str::uuid()->toString(),
                'workflow_version' => 2,
                'template_id' => $template->id,
                'schedule_slot_id' => $lockedSlot->id,
                'occurrence_period_key' => $period['key'],
                'game_id' => $template->game_id,
                'competition_type' => $template->competition_type,
                'name' => ($settings['name'] ?? $template->name).' - '.$startLocal->format('M d, Y g:i A'),
                'slug' => Str::slug($template->name).'-'.$period['key'].'-'.$lockedSlot->id,
                'status' => TournamentStatus::REGISTRATION_OPEN,
                'entry_fee' => $settings['entry_fee'] ?? $template->entry_fee,
                'prize_pool' => '0.00',
                'advertised_prize_pool' => null,
                'max_participants' => (int) ($settings['max_teams'] ?? $template->max_participants),
                'min_participants' => 2,
                'team_size' => 1,
                'registration_open_at' => ($isOneTime ? $startLocal : $period['start'])->utc(),
                // Entries close at the advertised start. The schedule end is
                // the occurrence boundary/audit retention boundary, not a
                // second registration period.
                'registration_close_at' => $startLocal->utc(),
                'checkin_open_at' => $startLocal->utc(),
                'checkin_close_at' => $startLocal->utc(),
                'start_at' => $startLocal->utc(),
                'end_at' => $endLocal->utc(),
                'join_closes_at' => $startLocal->utc(),
                'timezone' => $template->timezone,
                'frequency' => $isOneTime ? 'one_time' : $frequency->value,
                'description' => $settings['description'] ?? $defaults?->description,
                'rules' => $settings['rules'] ?? $defaults?->rules,
                'banner_url' => $this->publicMediaUrl($settings['banner_url'] ?? ($template->competition_type === CompetitionType::HEAD_TO_HEAD
                    ? $defaults?->head_to_head_banner_path
                    : $defaults?->tournament_banner_path)),
                'platform_id' => $settings['platform_id'] ?? $defaults?->default_platform_id,
                'waiting_result_time' => (int) ($settings['waiting_result_time'] ?? 5),
                'round_duration_seconds' => $settings['round_duration_seconds'] ?? null,
                'winning_points' => (int) ($settings['winning_points'] ?? 15),
                'play_xp' => 10,
                'winner_bonus_xp' => (int) ($settings['winning_points'] ?? 15),
                'full_first_bps' => (int) ($settings['full_first_bps'] ?? 7500),
                'full_second_bps' => (int) ($settings['full_second_bps'] ?? 1500),
                'full_platform_bps' => 1000,
                'underfilled_first_bps' => 8500,
                'underfilled_platform_bps' => 1500,
                'financial_calculation_version' => 2,
                'is_auto_cancel_underfilled' => false,
                'is_featured' => (bool) ($settings['is_featured'] ?? false),
                'created_by' => $template->created_by ?? $creator->id,
            ]);
        }, 3);
    }

    private function scheduledStart(
        TournamentScheduleSlot $slot,
        RecurrenceFrequency $frequency,
        CarbonImmutable $periodStart,
    ): CarbonImmutable {
        [$hour, $minute, $second] = array_map('intval', array_pad(explode(':', $slot->local_start_time), 3, '0'));
        $date = match ($frequency) {
            RecurrenceFrequency::DAILY => $periodStart,
            RecurrenceFrequency::WEEKLY => $periodStart->addDays(max(0, min(6, (int) ($slot->day_of_week ?? 0)))),
            RecurrenceFrequency::MONTHLY => $periodStart->day(min(
                max(1, (int) ($slot->day_of_month ?? 1)),
                $periodStart->daysInMonth,
            )),
        };

        return $date->setTime($hour, $minute, $second);
    }

    private function scheduledEnd(
        TournamentScheduleSlot $slot,
        CarbonImmutable $startLocal,
        CarbonImmutable $periodEnd,
        string $timezone,
    ): CarbonImmutable {
        if ($slot->schedule_start_at === null || $slot->schedule_end_at === null) {
            return $periodEnd;
        }

        $anchorStart = CarbonImmutable::instance($slot->schedule_start_at)->setTimezone($timezone);
        $anchorEnd = CarbonImmutable::instance($slot->schedule_end_at)->setTimezone($timezone);
        $seconds = max(1, $anchorStart->diffInSeconds($anchorEnd, false));

        return $startLocal->addSeconds($seconds);
    }

    private function publicMediaUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://', '/']) ? $path : '/storage/'.ltrim($path, '/');
    }
}
