<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\PaymentCheckout;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Support\Currency;
use Illuminate\Support\Str;
use LogicException;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function __construct(private DepositFeeCalculator $feeCalculator) {}

    public function createDepositSession(User $user, Wallet $wallet, float $amount): string
    {
        if ($amount <= 0) {
            throw new LogicException('Deposit amount must be greater than zero.');
        }

        $configuration = app(PaymentSettings::class)->capture('stripe');
        $secret = $configuration->credential('secret');
        if ($secret === '') {
            throw new LogicException('Stripe secret key is not configured.');
        }

        $breakdown = $this->feeCalculator->calculate($amount);
        $currency = Currency::lockForFinancialActivity();
        $checkout = PaymentCheckout::query()->create([
            'id' => Str::uuid()->toString(), 'wallet_id' => $wallet->id, 'provider' => 'stripe',
            'gateway_configuration_id' => $configuration->id, 'currency' => $currency,
            'amount' => $breakdown['credit'], 'fee_amount' => $breakdown['fee'], 'total' => $breakdown['total'],
        ]);
        $stripe = new StripeClient($secret);

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => (string) $user->getKey(),
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => 'GamersRival Wallet Deposit',
                        ],
                        'unit_amount' => (int) round((float) $breakdown['total'] * 100),
                    ],
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'type' => 'wallet_deposit',
                'checkout_id' => $checkout->id,
                'user_id' => (string) $user->getKey(),
                'user_uuid' => (string) $user->uuid,
                'wallet_id' => (string) $wallet->getKey(),
                'wallet_uuid' => (string) $wallet->uuid,
                'wallet_credit_amount' => $breakdown['credit'],
                'processing_fee_amount' => $breakdown['fee'],
                'currency' => $currency,
            ],
            'success_url' => route('wallet', ['stripe_deposit' => 'success'], true),
            'cancel_url' => route('wallet', ['stripe_deposit' => 'cancelled'], true),
        ]);

        $url = $session->url ?? null;
        if (! is_string($url) || $url === '') {
            throw new LogicException('Stripe did not return a Checkout URL.');
        }

        if ($session->id === '') {
            throw new LogicException('Stripe did not return a Checkout session identifier.');
        }
        $checkout->update(['provider_reference' => $session->id]);

        return $url;
    }
}
