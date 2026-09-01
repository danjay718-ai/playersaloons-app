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

        if (! $full) {
            $platformBps = (int) ($tournament->underfilled_platform_bps ?? 1500);
            $firstBps = (int) ($tournament->underfilled_first_bps ?? 8500);
            $secondBps = 0;
        } elseif ($maximum <= 4) {
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
