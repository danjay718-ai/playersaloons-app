<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Contracts\PrizePolicy;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Support\DecimalMoney;
use LogicException;

final class V2PrizePolicy implements PrizePolicy
{
    public function calculate(Tournament $tournament, int $joinedEntries): array
    {
        $maximum = (int) $tournament->max_participants;
        $full = $joinedEntries >= $maximum;
        $gross = DecimalMoney::toMinor((string) $tournament->entry_fee) * $joinedEntries;

        // Follow the size of the field that actually competed. Small fields
        // pay the champion only; fields with at least five entries use the
        // configured full-field split. The former underfilled 85/15 policy is
        // intentionally no longer used.
        if ($maximum <= 4 || $joinedEntries <= 4) {
            $platformBps = 1000;
            $firstBps = 9000;
            $secondBps = 0;
        } else {
            $platformBps = (int) ($tournament->full_platform_bps ?? 1000);
            $firstBps = (int) ($tournament->full_first_bps ?? 7500);
            $secondBps = (int) ($tournament->full_second_bps ?? 1500);
        }

        if ($platformBps + $firstBps + $secondBps !== 10000) {
            throw new LogicException('Tournament payout percentages must total 100%.');
        }

        $commission = DecimalMoney::percentage($gross, $platformBps);
        $second = DecimalMoney::percentage($gross, $secondBps);
        $first = $gross - $commission - $second;

        return [
            'full' => $full,
            'joined_entries' => $joinedEntries,
            'gross_minor' => $gross,
            'commission_minor' => $commission,
            'first_minor' => $first,
            'second_minor' => $second,
            'gross' => DecimalMoney::format($gross),
            'commission' => DecimalMoney::format($commission),
            'first' => DecimalMoney::format($first),
            'second' => DecimalMoney::format($second),
        ];
    }
}
