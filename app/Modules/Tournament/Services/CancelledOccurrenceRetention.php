<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Models\Tournament;
use Carbon\CarbonImmutable;

final class CancelledOccurrenceRetention
{
    public function eligibleAt(Tournament $tournament): CarbonImmutable
    {
        $timezone = $tournament->timezone ?: 'UTC';
        $start = CarbonImmutable::instance($tournament->start_at)->setTimezone($timezone);
        $local = match ($tournament->frequency) {
            'daily' => $start->endOfDay()->addDay(),
            'weekly' => $start->endOfWeek()->addDay(),
            'monthly' => $start->endOfMonth()->addDay(),
            default => CarbonImmutable::instance($tournament->end_at ?? $tournament->start_at)
                ->setTimezone($timezone)->addDay(),
        };

        return $local->utc();
    }

    public function isEligible(Tournament $tournament, ?CarbonImmutable $now = null): bool
    {
        return $this->eligibleAt($tournament)->lessThanOrEqualTo($now ?? CarbonImmutable::now('UTC'));
    }
}
