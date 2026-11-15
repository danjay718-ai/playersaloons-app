<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Operations\Services\SystemSettings;
use App\Shared\Support\DecimalMoney;

class DepositFeeCalculator
{
    /** @return array{credit: string, fee: string, total: string} */
    public function calculate(float $amount): array
    {
        $total = max(0, DecimalMoney::toMinor($amount));
        $enabled = filter_var(app(SystemSettings::class)->get('deposit_fee.enabled') ?? false, FILTER_VALIDATE_BOOL);
        $fixed = $enabled ? DecimalMoney::toMinor((string) app(SystemSettings::class)->get('deposit_fee.fixed', '0.00')) : 0;
        $percentage = $enabled ? DecimalMoney::toMinor((string) app(SystemSettings::class)->get('deposit_fee.percentage', '0.00')) : 0;
        $fee = min($total, $fixed + DecimalMoney::percentage($total, $percentage));

        return [
            'credit' => DecimalMoney::format($total - $fee),
            'fee' => DecimalMoney::format($fee),
            'total' => DecimalMoney::format($total),
        ];
    }
}
