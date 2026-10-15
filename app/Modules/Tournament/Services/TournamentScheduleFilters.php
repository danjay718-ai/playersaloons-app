<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Models\Tournament;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Validator;

/** Apply admin schedule filters in the same wall-clock timezone as the sidebar. */
final class TournamentScheduleFilters
{
    private ?array $offsetPeriods = null;

    /** @param array<string, string> $filters */
    public function apply(Builder|Relation $query, array $filters): void
    {
        $daily = ($filters['tab'] ?? 'all') === 'daily';
        $values = $daily
            ? ['start_time' => $filters['start_time'] ?? '']
            : ['start_date' => $filters['start_date'] ?? '', 'end_date' => $filters['end_date'] ?? ''];
        if (Validator::make($values, [
            'start_time' => ['nullable', 'date_format:H:i'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ])->fails()) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($daily) {
            if (($values['start_time'] ?? '') !== '') {
                $this->applyTime($query, $values['start_time']);
            }

            return;
        }

        foreach (['start_date', 'end_date'] as $field) {
            if (($values[$field] ?? '') === '') {
                continue;
            }
            $boundary = CarbonImmutable::createFromFormat('!Y-m-d', $values[$field], TournamentTimezone::AMSTERDAM);
            $query->where('start_at', $field === 'start_date' ? '>=' : '<',
                ($field === 'start_date' ? $boundary : $boundary->addDay())->utc());
        }
    }

    private function applyTime(Builder|Relation $query, string $time): void
    {
        // Each IANA transition supplies the actual UTC offset for its interval.
        // This works across summer/winter time without database timezone tables.
        $periods = $this->offsetPeriods ??= $this->offsetPeriods();
        if ($periods === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(function (Builder $query) use ($periods, $time): void {
            foreach ($periods as $period) {
                $utcTime = CarbonImmutable::createFromFormat('!H:i', $time, 'UTC')
                    ->subSeconds($period['offset'])->format('H:i:s');
                $query->orWhere(function (Builder $query) use ($period, $utcTime): void {
                    $query->where('start_at', '>=', $period['start'])
                        ->where('start_at', '<', $period['end'])
                        ->whereTime('start_at', $utcTime);
                });
            }
        });
    }

    private function offsetPeriods(): array
    {
        $bounds = Tournament::query()->selectRaw('MIN(start_at) as earliest_start, MAX(start_at) as latest_start')->first();
        if ($bounds?->getAttribute('earliest_start') === null) {
            return [];
        }
        $start = CarbonImmutable::parse($bounds->getAttribute('earliest_start'), 'UTC');
        $end = CarbonImmutable::parse($bounds->getAttribute('latest_start'), 'UTC')->addSecond();
        $transitions = (new DateTimeZone(TournamentTimezone::AMSTERDAM))->getTransitions($start->timestamp, $end->timestamp) ?: [];
        $periods = [];
        foreach ($transitions as $index => $transition) {
            $periods[] = [
                'start' => CarbonImmutable::createFromTimestampUTC($transition['ts']),
                'end' => isset($transitions[$index + 1]) ? CarbonImmutable::createFromTimestampUTC($transitions[$index + 1]['ts']) : $end,
                'offset' => $transition['offset'],
            ];
        }

        return $periods;
    }
}
