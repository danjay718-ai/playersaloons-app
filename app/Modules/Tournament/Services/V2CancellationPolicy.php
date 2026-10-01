<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Support\DecimalMoney;
use Carbon\CarbonInterface;

final class V2CancellationPolicy
{
    public static function isOpen(Tournament $tournament): bool
    {
        return (int) $tournament->workflow_version === 2
            && $tournament->start_at !== null
            && now()->lessThan($tournament->start_at)
            && ! in_array($tournament->status, [TournamentStatus::DRAFT, TournamentStatus::ONGOING,
                TournamentStatus::COMPLETED, TournamentStatus::CANCELLED, TournamentStatus::REFUNDED], true);
    }

    /** @return array{fee: string, refund: string} */
    public static function amounts(Tournament $tournament, CarbonInterface $requestedAt): array
    {
        $entry = DecimalMoney::toMinor((string) ($tournament->entry_fee ?? '0.00'));
        $late = $tournament->start_at !== null
            && $requestedAt->greaterThan($tournament->start_at->copy()->subMinutes(30));
        $fee = $late ? DecimalMoney::percentage($entry, 1000) : 0;

        return ['fee' => DecimalMoney::format($fee), 'refund' => DecimalMoney::format($entry - $fee)];
    }
}
