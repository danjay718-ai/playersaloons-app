<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Operations\Services\SystemSettings;
use App\Shared\Support\DecimalMoney;

class WithdrawalFeeCalculator
{
    /** @return array{payout: string, fee: string, total: string} */
    public function calculate(float $amount): array
    {
        $total = max(0, DecimalMoney::toMinor($amount));
        $settings = app(SystemSettings::class);
        $enabled = filter_var($settings->get('withdrawal_fee.enabled', false), FILTER_VALIDATE_BOOL);
        $percentage = $enabled ? DecimalMoney::toMinor((string) $settings->get('withdrawal_fee.percentage', '10.00')) : 0;
        $fee = min($total, DecimalMoney::percentage($total, $percentage));

        return ['payout' => DecimalMoney::format($total - $fee), 'fee' => DecimalMoney::format($fee), 'total' => DecimalMoney::format($total)];
    }
}
