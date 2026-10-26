<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\PaymentCheckout;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Support\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LogicException;

class PayopCheckoutService
{
    public function createDepositSession(User $user, Wallet $wallet, float $amount): string
    {
        if ($amount < 1 || $amount > 10000 || ! is_finite($amount) || (int) $wallet->user_id !== (int) $user->getKey()) {
            throw new LogicException('Invalid deposit request.');
        }

        $settings = app(PaymentSettings::class);
        $publicKey = $settings->credential('payop.public_key');
        $secret = $settings->credential('payop.secret');
        if ($publicKey === '' || $secret === '') {
            throw new LogicException('PayOp credentials are not configured.');
        }

        $breakdown = app(DepositFeeCalculator::class)->calculate($amount);
        $checkout = PaymentCheckout::query()->create([
            'id' => Str::uuid()->toString(), 'wallet_id' => $wallet->getKey(), 'provider' => 'payop',
            'currency' => Currency::code(), 'amount' => $breakdown['credit'],
            'fee_amount' => $breakdown['fee'], 'total' => $breakdown['total'],
        ]);

        // Do not retry invoice creation: a timeout may follow a successful remote creation.
        $response = Http::acceptJson()->asJson()->connectTimeout(5)->timeout(20)->post('https://api.payop.com/v1/invoices/create', [
            'publicKey' => $publicKey,
            'order' => ['id' => $checkout->id, 'amount' => $checkout->total, 'currency' => $checkout->currency,
                'description' => 'PlayerSaloons wallet deposit', 'items' => []],
            'signature' => hash('sha256', implode(':', [$checkout->total, $checkout->currency, $checkout->id, $secret])),
            'payer' => ['email' => $user->email],
            'language' => 'en',
            'resultUrl' => route('wallet', ['deposit' => 'submitted']),
            'failPath' => route('wallet', ['deposit' => 'cancelled']),
        ]);

        $invoice = $response->header('identifier') ?: $response->json('data');
        if (! $response->successful() || (int) $response->json('status') !== 1 || ! is_string($invoice) || ! Str::isUuid($invoice)) {
            throw new LogicException('PayOp could not create a payment invoice.');
        }
        $checkout->update(['provider_reference' => $invoice]);

        return 'https://checkout.payop.com/en/payment/invoice-preprocessing/'.$invoice;
    }
}
