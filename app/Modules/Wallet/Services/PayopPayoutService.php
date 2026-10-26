<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Wallet\Exceptions\PayoutSubmissionRejectedException;
use App\Modules\Wallet\Models\Withdrawal;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LogicException;

class PayopPayoutService
{
    private function client(): PendingRequest
    {
        $token = app(PaymentSettings::class)->credential('payop.payout_token');
        if ($token === '') {
            throw new LogicException('PayOp payout token is not configured.');
        }

        return Http::acceptJson()->asJson()->withToken($token)->connectTimeout(5)->timeout(20);
    }

    public function certificate(): string
    {
        $key = base64_decode(app(PaymentSettings::class)->credential('payop.payout_certificate'), true);
        if (! function_exists('sodium_crypto_box_seal') || $key === false || strlen($key) !== 32) {
            throw new LogicException('A valid PayOp payout certificate and Sodium are required.');
        }
        sodium_crypto_box_seal('certificate-validation', $key);

        return $key;
    }

    public function assertBankPayoutAvailable(string $currency): void
    {
        $this->certificate();
        $response = $this->client()->get('https://api.payop.com/v1/instrument-settings/payment-methods/available-withdrawal-for-user');
        if (! $response->successful() || (int) $response->json('status') !== 1) {
            throw new LogicException('Unable to verify PayOp payout availability.');
        }
        foreach ((array) $response->json('data') as $method) {
            if (is_array($method) && (int) ($method['type'] ?? 0) === 20 && in_array($currency, $method['currencies'] ?? [], true)) {
                return;
            }
        }
        throw new LogicException('PayOp bank payouts are not available for this currency and merchant.');
    }

    public function submit(Withdrawal $withdrawal): string
    {
        $recipient = $withdrawal->payout_recipient;
        if (! is_array($recipient)) {
            throw new LogicException('Withdrawal bank details are missing.');
        }
        $payload = [[
            'method' => 20, 'type' => 1, 'amount' => $withdrawal->amount, 'currency' => $withdrawal->payout_currency,
            'additionalData' => ['beneficiary' => ['account' => $recipient['iban'], 'name' => $recipient['name']], 'email' => $recipient['email']],
            'clientId' => $withdrawal->uuid,
            'metadata' => ['internalWithdrawalId' => $withdrawal->uuid],
        ]];
        $encrypted = sodium_crypto_box_seal(json_encode($payload, JSON_THROW_ON_ERROR), $this->certificate());
        // A stable key prevents double payment. Never retry with a fresh key after an uncertain response.
        $response = $this->client()->withHeader('idempotency-key', $withdrawal->uuid)
            ->post('https://api.payop.com/v1/withdrawals/create-mass', ['data' => base64_encode($encrypted)]);
        $reference = $response->json('data.0.id');
        if (in_array($response->status(), [400, 401, 403, 422], true)) {
            throw new PayoutSubmissionRejectedException('PayOp refused the payout submission. The withdrawal funds have been returned.');
        }
        if (! $response->successful() || (int) $response->json('status') !== 1 || ! is_string($reference) || ! Str::isUuid($reference)) {
            throw new LogicException('PayOp payout submission needs reconciliation. Do not resend or pay manually.');
        }

        return $reference;
    }

    public function fetch(string $reference): array
    {
        $response = $this->client()->get('https://api.payop.com/v1/withdrawals/user-withdrawals', ['query' => ['identifier' => $reference]]);
        if (! $response->successful() || (int) $response->json('status') !== 1 || ! is_array($response->json('data'))) {
            throw new LogicException('PayOp payout verification is unavailable.');
        }
        foreach ($response->json('data') as $item) {
            if (is_array($item) && ($item['identifier'] ?? $item['id'] ?? null) === $reference) {
                return $item;
            }
        }

        throw new LogicException('PayOp payout details are unavailable.');
    }
}
