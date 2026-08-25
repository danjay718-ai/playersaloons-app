<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTemplatePrize;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;

class PrizeCalculationService
{
    /**
     * Calculate prize distributions and platform rake.
     *
     * @return array{
     *     total_entry_fees: float,
     *     confirmed_count: int,
     *     rake_amount: float,
     *     prize_pool: float,
     *     attendance_multiplier: float,
     *     distributions: array<int, float>,
     *     rounding_remainder: float,
     * }
     */
    public function calculate(Tournament $tournament): array
    {
        $confirmedCount = $tournament->registrations()
            ->where('status', RegistrationStatus::CONFIRMED)
            ->count();
        $paidCount = $tournament->registrations()
            ->where('status', RegistrationStatus::CONFIRMED)
            ->where('payment_status', PaymentStatus::PAID)
            ->count();

        $entryFee = (float) ($tournament->entry_fee ?? '0.00');
        $totalEntryFees = $paidCount * $entryFee;

        // Retrieve platform rake percentage
        $rakeSetting = SystemSetting::query()
            ->where('key', 'platform.rake_percentage')
            ->value('value');
        $rakePercentage = $rakeSetting !== null ? (float) $rakeSetting : 10.0;

        $rakeAmount = round($totalEntryFees * ($rakePercentage / 100.0), 2);
        $calculatedPrizePool = round($totalEntryFees - $rakeAmount, 2);

        $attendanceMultiplier = $this->attendanceMultiplier($tournament, $confirmedCount);
        $advertisedPrizePool = (float) ($tournament->advertised_prize_pool ?? $tournament->prize_pool ?? 0.00);
        $attendanceAdjustedPrizePool = round($advertisedPrizePool * $attendanceMultiplier, 2);

        // Once registration closes, prize_pool is the locked final amount. Until
        // then, show the live projection based on confirmed participants.
        $prizePool = $tournament->registration_locked_at !== null
            ? (float) $tournament->prize_pool
            : max($attendanceAdjustedPrizePool, $calculatedPrizePool);

        $manualPrizes = array_filter([
            1 => $tournament->prize_1st,
            2 => $tournament->prize_2nd,
            3 => $tournament->prize_3rd,
        ], static fn ($amount): bool => $amount !== null && (float) $amount > 0.0);

        // Get template prizes
        $prizes = $tournament->template
            ? $tournament->template->prizes()->orderBy('position')->get()
            : collect();

        $distributions = [];
        $totalAllocated = 0.0;

        if ($manualPrizes !== []) {
            foreach ($manualPrizes as $rank => $amount) {
                $distributionAmount = round((float) $amount * $attendanceMultiplier, 2);
                $distributions[$rank] = $distributionAmount;
                $totalAllocated += $distributionAmount;
            }
        } elseif ($prizes->isEmpty()) {
            // Default to 100% to Rank 1
            $distributions[1] = $prizePool;
            $totalAllocated = $prizePool;
        } else {
            /** @var TournamentTemplatePrize $prize */
            foreach ($prizes as $prize) {
                $rank = $prize->position;
                if ($prize->percentage !== null) {
                    $amount = round($prizePool * ((float) $prize->percentage / 100.0), 2);
                } elseif ($prize->amount !== null) {
                    $amount = round((float) $prize->amount * $attendanceMultiplier, 2);
                } else {
                    $amount = 0.00;
                }
                $distributions[$rank] = $amount;
                $totalAllocated += $amount;
            }
        }

        $remainder = round($prizePool - $totalAllocated, 2);

        return [
            'total_entry_fees' => $totalEntryFees,
            'confirmed_count' => $confirmedCount,
            'rake_amount' => $rakeAmount,
            'prize_pool' => $prizePool,
            'attendance_multiplier' => $attendanceMultiplier,
            'distributions' => $distributions,
            'rounding_remainder' => $remainder,
        ];
    }

    public function attendanceMultiplier(Tournament $tournament, int $confirmedCount): float
    {
        $minimum = max(1, (int) $tournament->min_participants);
        $maximum = max($minimum, (int) $tournament->max_participants);
        $participants = min($maximum, max(0, $confirmedCount));

        if ($maximum === $minimum) {
            return $participants >= $minimum ? 1.0 : 0.5;
        }

        if ($participants <= $minimum) {
            return 0.5;
        }

        return round(0.5 + (0.5 * (($participants - $minimum) / ($maximum - $minimum))), 6);
    }
}
