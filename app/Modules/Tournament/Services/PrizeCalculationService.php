<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Services;

use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentTemplatePrize;

class PrizeCalculationService
{
    /**
     * Calculate prize distributions and platform rake.
     *
     * @return array{
     *     total_entry_fees: float,
     *     rake_amount: float,
     *     prize_pool: float,
     *     distributions: array<int, float>,
     *     rounding_remainder: float,
     * }
     */
    public function calculate(Tournament $tournament): array
    {
        $paidCount = $tournament->registrations()
            ->where('status', \App\Shared\Enums\RegistrationStatus::CONFIRMED)
            ->where('payment_status', \App\Shared\Enums\PaymentStatus::PAID)
            ->count();

        $entryFee = (float) ($tournament->entry_fee ?? '0.00');
        $totalEntryFees = $paidCount * $entryFee;

        // Retrieve platform rake percentage
        $rakeSetting = SystemSetting::query()
            ->where('key', 'platform.rake_percentage')
            ->value('value');
        $rakePercentage = $rakeSetting !== null ? (float) $rakeSetting : 10.0;

        $rakeAmount = round($totalEntryFees * ($rakePercentage / 100.0), 2);
        $prizePool = round($totalEntryFees - $rakeAmount, 2);

        // Get template prizes
        $prizes = $tournament->template
            ? $tournament->template->prizes()->orderBy('position')->get()
            : collect();

        $distributions = [];
        $totalAllocated = 0.0;

        if ($prizes->isEmpty()) {
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
                    $amount = (float) $prize->amount;
                } else {
                    $amount = 0.00;
                }
                $distributions[$rank] = $amount;
                $totalAllocated += $amount;
            }
        }

        $remainder = round($prizePool - $totalAllocated, 2);

        return [
            'total_entry_fees'   => $totalEntryFees,
            'rake_amount'        => $rakeAmount,
            'prize_pool'         => $prizePool,
            'distributions'      => $distributions,
            'rounding_remainder' => $remainder,
        ];
    }
}
