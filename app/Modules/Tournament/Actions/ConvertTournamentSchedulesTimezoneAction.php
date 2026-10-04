<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ConvertTournamentSchedulesTimezoneAction
{
    /** Convert future schedules while preserving their displayed wall-clock times. */
    public function execute(string $fromTimezone, string $toTimezone): void
    {
        if ($fromTimezone === $toTimezone) {
            return;
        }

        DB::transaction(function () use ($fromTimezone, $toTimezone): void {
            $now = now();

            DB::table('tournament_templates')
                ->where('timezone', $fromTimezone)
                ->orderBy('id')
                ->chunkById(100, function ($templates) use ($fromTimezone, $toTimezone, $now): void {
                    foreach ($templates as $template) {
                        DB::table('tournament_templates')->where('id', $template->id)->update([
                            'timezone' => $toTimezone,
                            // Keep the recurrence anchor's wall-clock value too. Even a
                            // past anchor is used to calculate the next occurrence.
                            'next_run_at' => $template->next_run_at === null
                                ? null
                                : $this->convert($template->next_run_at, $fromTimezone, $toTimezone),
                            'updated_at' => $now,
                        ]);

                        DB::table('tournament_schedule_slots')
                            ->where('tournament_template_id', $template->id)
                            ->where('is_active', true)
                            ->whereNull('deleted_at')
                            ->orderBy('id')
                            ->chunkById(100, function ($slots) use ($fromTimezone, $toTimezone, $now): void {
                                foreach ($slots as $slot) {
                                    $startAt = $slot->schedule_start_at;
                                    $endAt = $slot->schedule_end_at;
                                    DB::table('tournament_schedule_slots')->where('id', $slot->id)->update([
                                        'schedule_start_at' => $startAt === null ? null : $this->convert($startAt, $fromTimezone, $toTimezone),
                                        'schedule_end_at' => $endAt === null ? null : $this->convert($endAt, $fromTimezone, $toTimezone),
                                        'updated_at' => $now,
                                    ]);
                                }
                            });
                    }
                });

            DB::table('tournaments')
                ->where('timezone', $fromTimezone)
                ->whereNotNull('start_at')
                ->where('start_at', '>', $now)
                ->orderBy('id')
                ->chunkById(100, function ($tournaments) use ($fromTimezone, $toTimezone, $now): void {
                    $dateColumns = [
                        'registration_open_at', 'registration_close_at', 'checkin_open_at', 'checkin_close_at',
                        'start_at', 'end_at', 'join_closes_at',
                    ];

                    foreach ($tournaments as $tournament) {
                        $values = ['timezone' => $toTimezone, 'updated_at' => $now];
                        foreach ($dateColumns as $column) {
                            $value = $tournament->{$column} ?? null;
                            $values[$column] = $value === null ? null : $this->convert($value, $fromTimezone, $toTimezone);
                        }
                        DB::table('tournaments')->where('id', $tournament->id)->update($values);
                    }
                });
        });
    }

    private function convert(string $value, string $fromTimezone, string $toTimezone): string
    {
        return CarbonImmutable::parse($value, 'UTC')
            ->setTimezone($fromTimezone)
            ->shiftTimezone($toTimezone)
            ->utc()
            ->format('Y-m-d H:i:s');
    }
}
