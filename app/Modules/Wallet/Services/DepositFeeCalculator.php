<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Operations\Models\SystemSetting;

class DepositFeeCalculator
{
    /** @return array{credit: string, fee: string, total: string} */
    public function calculate(float $credit): array
    {
        $credit = max(0, $credit);
        $enabled = filter_var(SystemSetting::query()->where('key', 'deposit_fee.enabled')->value('value') ?? false, FILTER_VALIDATE_BOOL);
        $fixed = $enabled ? (float) (SystemSetting::query()->where('key', 'deposit_fee.fixed')->value('value') ?? 0) : 0;
        $percentage = $enabled ? (float) (SystemSetting::query()->where('key', 'deposit_fee.percentage')->value('value') ?? 0) : 0;
        $fee = round($fixed + ($credit * $percentage / 100), 2);

        return [
            'credit' => number_format($credit, 2, '.', ''),
            'fee' => number_format($fee, 2, '.', ''),
            'total' => number_format($credit + $fee, 2, '.', ''),
        ];
    }
}
