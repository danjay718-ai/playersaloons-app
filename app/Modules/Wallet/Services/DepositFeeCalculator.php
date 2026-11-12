<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Operations\Services\SystemSettings;

class DepositFeeCalculator
{
    /** @return array{credit: string, fee: string, total: string} */
    public function calculate(float $credit): array
    {
        $credit = max(0, $credit);
        $enabled = filter_var(app(SystemSettings::class)->get('deposit_fee.enabled') ?? false, FILTER_VALIDATE_BOOL);
        $fixed = $enabled ? (float) (app(SystemSettings::class)->get('deposit_fee.fixed') ?? 0) : 0;
        $percentage = $enabled ? (float) (app(SystemSettings::class)->get('deposit_fee.percentage') ?? 0) : 0;
        $fee = round($fixed + ($credit * $percentage / 100), 2);

        return [
            'credit' => number_format($credit, 2, '.', ''),
            'fee' => number_format($fee, 2, '.', ''),
            'total' => number_format($credit + $fee, 2, '.', ''),
        ];
    }
}
