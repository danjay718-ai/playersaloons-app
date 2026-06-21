<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Wallet;
use LogicException;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function createDepositSession(User $user, Wallet $wallet, float $amount): string
    {
        if ($amount <= 0) {
            throw new LogicException('Deposit amount must be greater than zero.');
        }

        $secret = (string) config('services.stripe.secret', '');
        if ($secret === '') {
            throw new LogicException('Stripe secret key is not configured.');
        }

        $stripe = new StripeClient($secret);

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => (string) $user->getKey(),
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'PlayerSaloons Wallet Deposit',
                        ],
                        'unit_amount' => (int) round($amount * 100),
                    ],
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'type' => 'wallet_deposit',
                'user_id' => (string) $user->getKey(),
                'user_uuid' => (string) $user->uuid,
                'wallet_id' => (string) $wallet->getKey(),
                'wallet_uuid' => (string) $wallet->uuid,
            ],
            'success_url' => route('wallet', ['stripe_deposit' => 'success'], true),
            'cancel_url' => route('wallet', ['stripe_deposit' => 'cancelled'], true),
        ]);

        $url = $session->url ?? null;
        if (! is_string($url) || $url === '') {
            throw new LogicException('Stripe did not return a Checkout URL.');
        }

        return $url;
    }
}
