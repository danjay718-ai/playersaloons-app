<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Wallet;
use LogicException;

class DepositCheckoutService
{
    public function createDepositSession(User $user, Wallet $wallet, float $amount): string
    {
        $settings = app(PaymentSettings::class);
        if (! $settings->enabled()) {
            throw new LogicException('Deposits are disabled.');
        }

        return match ($settings->provider()) {
            'stripe' => app(StripeCheckoutService::class)->createDepositSession($user, $wallet, $amount),
            'payop' => app(PayopCheckoutService::class)->createDepositSession($user, $wallet, $amount),
            default => throw new LogicException('Unsupported payment gateway.'),
        };
    }
}
