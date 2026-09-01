<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Shared\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;

final class OccurrencePeriod
{
    /** @return array{key:string,start:CarbonImmutable,end:CarbonImmutable} */
    public function current(RecurrenceFrequency $frequency, string $timezone, ?CarbonImmutable $now = null): array
    {
        $local = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone);

        [$key, $start, $end] = match ($frequency) {
            RecurrenceFrequency::DAILY => [
                $local->format('Y-m-d'),
                $local->startOfDay(),
                $local->startOfDay()->addDay(),
            ],
            RecurrenceFrequency::WEEKLY => [
                $local->format('o-\\WW'),
                $local->startOfWeek(),
                $local->startOfWeek()->addWeek(),
            ],
            RecurrenceFrequency::MONTHLY => [
                $local->format('Y-m'),
                $local->startOfMonth(),
                $local->startOfMonth()->addMonth(),
            ],
        };

        return ['key' => $key, 'start' => $start, 'end' => $end];
    }
}
