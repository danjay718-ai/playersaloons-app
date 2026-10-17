<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
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

    public function test_webhook_credits_wallet_amount_and_persists_processing_fee(): void
    {
        $wallet = $this->createWallet('0.00');
        $payload = $this->checkoutSessionPayload('cs_test_fee_123', 5200, [
            'wallet_id' => (string) $wallet->getKey(),
            'wallet_credit_amount' => '50.00',
            'processing_fee_amount' => '2.00',
        ]);

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame('50.00', $wallet->fresh()?->cached_balance);
        $this->assertDatabaseHas('deposits', ['provider_reference' => 'cs_test_fee_123', 'amount' => '50.00', 'fee_amount' => '2.00']);
    }

    public function test_webhook_rejects_mismatched_fee_metadata(): void
    {
        $wallet = $this->createWallet('0.00');
        $payload = $this->checkoutSessionPayload('cs_test_fee_tampered', 5000, [
            'wallet_id' => (string) $wallet->getKey(),
            'wallet_credit_amount' => '50.00',
            'processing_fee_amount' => '2.00',
        ]);

        $this->postStripeWebhook($payload)->assertStatus(422);
        $this->assertSame('0.00', $wallet->fresh()?->cached_balance);
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
    public function test_webhook_accepts_euro_and_pound_and_keeps_retries_idempotent_after_a_currency_change(): void
    {
        $wallet = $this->createWallet('0.00');
        foreach (['EUR', 'GBP'] as $code) {
            SystemSetting::query()->updateOrCreate(['key' => 'currency.code'], ['value' => $code]);
            $payload = $this->checkoutSessionPayload('cs_currency_'.$code, 1000, [
                'wallet_id' => (string) $wallet->id, 'currency' => $code,
            ], strtolower($code));
            $this->postStripeWebhook($payload)->assertOk();
            $this->postStripeWebhook($payload)->assertOk();
        }
        self::assertSame('20.00', $wallet->fresh()->cached_balance);
        $euroRetry = $this->checkoutSessionPayload('cs_currency_EUR', 1000, [
            'wallet_id' => (string) $wallet->id, 'currency' => 'EUR',
        ], 'eur');
        $this->postStripeWebhook($euroRetry)->assertOk();
        self::assertSame('20.00', $wallet->fresh()->cached_balance);
    }

    public function test_webhook_rejects_a_payment_in_another_currency_without_crediting_the_wallet(): void
    {
        $wallet = $this->createWallet('0.00');
        SystemSetting::query()->updateOrCreate(['key' => 'currency.code'], ['value' => 'EUR']);
        $payload = $this->checkoutSessionPayload('cs_currency_wrong', 1000, [
            'wallet_id' => (string) $wallet->id, 'currency' => 'GBP',
        ], 'gbp');
        $this->postStripeWebhook($payload)->assertStatus(422);
        self::assertSame('0.00', $wallet->fresh()->cached_balance);
        self::assertSame(0, Deposit::query()->count());
    }

    private function checkoutSessionPayload(string $sessionId, int $amountTotal, array $metadata, string $currency = 'usd'): string
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
                    'currency' => $currency,
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
