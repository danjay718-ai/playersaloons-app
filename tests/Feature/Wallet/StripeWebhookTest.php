<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Deposit;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
    }

    public function test_checkout_session_completed_credits_wallet(): void
    {
        $wallet = $this->createWallet('10.00');
        $payload = $this->checkoutSessionPayload('cs_test_deposit_123', 2500, [
            'wallet_id' => (string) $wallet->getKey(),
        ]);

        $this->postStripeWebhook($payload)
            ->assertOk()
            ->assertJson([
                'received' => true,
                'handled' => true,
            ]);

        $wallet->refresh();

        $this->assertSame('35.00', $wallet->getAttribute('cached_balance'));
        $this->assertDatabaseHas('deposits', [
            'wallet_id' => $wallet->getKey(),
            'amount' => '25.00',
            'provider' => 'stripe',
            'provider_reference' => 'cs_test_deposit_123',
            'status' => 'completed',
        ]);
    }

    public function test_checkout_session_webhook_is_idempotent(): void
    {
        $wallet = $this->createWallet('0.00');
        $payload = $this->checkoutSessionPayload('cs_test_idempotent_123', 1000, [
            'wallet_id' => (string) $wallet->getKey(),
        ]);

        $this->postStripeWebhook($payload)->assertOk();
        $this->postStripeWebhook($payload)->assertOk();

        $wallet->refresh();

        $this->assertSame('10.00', $wallet->getAttribute('cached_balance'));
        $this->assertSame(1, Deposit::query()->where('provider_reference', 'cs_test_idempotent_123')->count());
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = $this->checkoutSessionPayload('cs_test_bad_signature', 1000, [
            'wallet_id' => '1',
        ]);

        $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=invalid',
            ],
            $payload
        )->assertBadRequest();
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function checkoutSessionPayload(string $sessionId, int $amountTotal, array $metadata): string
    {
        return json_encode([
            'id' => 'evt_'.$sessionId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'amount_total' => $amountTotal,
                    'currency' => 'usd',
                    'payment_status' => 'paid',
                    'metadata' => $metadata,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function postStripeWebhook(string $payload)
    {
        return $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $this->signatureHeader($payload),
            ],
            $payload
        );
    }

    private function signatureHeader(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $this->webhookSecret);

        return "t={$timestamp},v1={$signature}";
    }

    private function createWallet(string $balance): Wallet
    {
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'username' => 'stripe-webhook-player',
            'email' => 'stripe-webhook-player@example.com',
            'password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);

        return Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->getKey(),
            'cached_balance' => $balance,
            'status' => WalletStatus::ACTIVE,
        ]);
    }
}
