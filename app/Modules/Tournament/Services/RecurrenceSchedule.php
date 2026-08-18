<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Shared\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class RecurrenceSchedule
{
    /**
     * Return the next wall-clock occurrence in the template timezone.
     *
     * Monthly schedules clamp (for example, day 31 becomes February 28/29)
     * instead of Carbon's default overflow into the following month.
     *
     * @param  array<string, mixed>  $settings
     */
    public function next(
        CarbonInterface $current,
        RecurrenceFrequency $frequency,
        string $timezone,
        array $settings = [],
    ): CarbonImmutable {
        $local = CarbonImmutable::instance($current)->setTimezone($timezone);

        return match ($frequency) {
            RecurrenceFrequency::DAILY => $local->addDay(),
            RecurrenceFrequency::WEEKLY => $local->addWeek(),
            RecurrenceFrequency::MONTHLY => $this->nextMonthly($local, $settings),
        };
    }

    /** @param array<string, mixed> $settings */
    private function nextMonthly(CarbonImmutable $current, array $settings): CarbonImmutable
    {
        $requestedDay = max(1, min(31, (int) ($settings['day_of_month'] ?? $current->day)));
        $nextMonth = $current->startOfMonth()->addMonth();

        return $nextMonth
            ->day(min($requestedDay, $nextMonth->daysInMonth))
            ->setTime($current->hour, $current->minute, $current->second);
    }
}
