<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Actions\ProcessDepositAction;
use App\Modules\Wallet\Models\Deposit;
use App\Modules\Wallet\Models\GatewayConfiguration;
use App\Modules\Wallet\Models\PaymentCheckout;
use App\Modules\Wallet\Models\PaymentGateway;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\PaymentSettings;
use App\Shared\Support\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeObject;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessDepositAction $processDeposit): JsonResponse
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');
        $data = json_decode($payload, true);
        $reference = is_array($data) ? ($data['data']['object']['id'] ?? null) : null;
        $checkoutId = is_array($data) ? ($data['data']['object']['metadata']['checkout_id'] ?? null) : null;
        $checkout = is_string($reference) ? PaymentCheckout::query()->where('provider', 'stripe')->where('provider_reference', $reference)->first() : null;
        if (! $checkout && is_string($checkoutId) && Str::isUuid($checkoutId)) {
            $checkout = PaymentCheckout::query()->where('provider', 'stripe')->whereNull('provider_reference')->find($checkoutId);
        }
        $secrets = [app(PaymentSettings::class)->credential('stripe.webhook_secret')];
        if ($checkout?->gatewayConfiguration) {
            $secrets[] = $checkout->gatewayConfiguration->credential('webhook_secret');
        }
        // Pre-migration sessions have no local checkout; retain their original verifier.
        foreach (GatewayConfiguration::query()->with('credentials')->where('legacy', true)
            ->where('payment_gateway_id', PaymentGateway::query()->where('provider', 'stripe')->select('id'))->get() as $configuration) {
            $secrets[] = $configuration->credential('webhook_secret');
        }
        $secrets = array_unique(array_filter($secrets));
        if ($secrets === []) {
            Log::error('Stripe webhook secret is not configured.');

            return response()->json(['message' => 'Stripe webhook is not configured.'], 500);
        }
        $event = null;
        foreach ($secrets as $secret) {
            try {
                $event = Webhook::constructEvent($payload, $signature, $secret);
                break;
            } catch (UnexpectedValueException) {
                return response()->json(['message' => 'Invalid Stripe webhook payload.'], 400);
            } catch (SignatureVerificationException) {
                // A pending checkout can still use an earlier account's endpoint.
            }
        }
        if ($event === null) {
            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        if ($event->type !== 'checkout.session.completed') {
            return response()->json(['received' => true, 'handled' => false]);
        }

        /** @var StripeObject $session */
        $session = $event->data->object;

        if (($session->payment_status ?? null) !== 'paid') {
            return response()->json(['received' => true, 'handled' => false]);
        }

        $wallet = $this->resolveWallet($session);
        if (! $wallet instanceof Wallet) {
            Log::warning('Stripe checkout session completed without a resolvable wallet.', [
                'checkout_session_id' => $session->id ?? null,
                'metadata' => $this->metadata($session),
            ]);

            return response()->json(['message' => 'Wallet metadata is missing or invalid.'], 422);
        }

        $chargedTotal = $this->amountFromSession($session);
        $metadata = $this->metadata($session);
        // Repeated events for a credited deposit stay idempotent after a settings change.
        if (Deposit::query()->where('provider', 'stripe')->where('provider_reference', (string) $session->id)->exists()) {
            return response()->json(['received' => true, 'handled' => true]);
        }
        $paidCurrency = strtoupper((string) ($session->currency ?? ''));
        if ($paidCurrency !== Currency::code()
            || (isset($metadata['currency']) && strtoupper((string) $metadata['currency']) !== $paidCurrency)) {
            return response()->json(['message' => 'Payment currency does not match the wallet currency.'], 422);
        }
        $creditAmount = isset($metadata['wallet_credit_amount']) && is_numeric($metadata['wallet_credit_amount'])
            ? (float) $metadata['wallet_credit_amount']
            : $chargedTotal;
        $feeAmount = isset($metadata['processing_fee_amount']) && is_numeric($metadata['processing_fee_amount'])
            ? (float) $metadata['processing_fee_amount']
            : 0.0;

        if ($creditAmount <= 0 || abs(($creditAmount + $feeAmount) - $chargedTotal) > 0.009) {
            return response()->json(['message' => 'Stripe checkout session amount is invalid.'], 422);
        }

        if ($checkout && ((int) $checkout->wallet_id !== (int) $wallet->id
            || $checkout->currency !== $paidCurrency
            || (int) round((float) $checkout->total * 100) !== (int) round($chargedTotal * 100)
            || (int) round((float) $checkout->amount * 100) !== (int) round($creditAmount * 100))) {
            return response()->json(['message' => 'Stripe checkout does not match the saved payment.'], 422);
        }
        DB::transaction(function () use ($checkout, $processDeposit, $wallet, $creditAmount, $feeAmount, $session): void {
            $locked = $checkout ? PaymentCheckout::query()->lockForUpdate()->findOrFail($checkout->id) : null;
            if ($locked && $locked->completed_at !== null) {
                return;
            }
            $processDeposit->execute($wallet, number_format($creditAmount, 2, '.', ''), 'stripe', (string) $session->id, number_format($feeAmount, 2, '.', ''));
            $locked?->update(['provider_reference' => (string) $session->id, 'completed_at' => now()]);
        });

        return response()->json(['received' => true, 'handled' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadata(StripeObject $session): array
    {
        $metadata = $session->metadata ?? [];

        if ($metadata instanceof StripeObject) {
            return $metadata->toArray();
        }

        return is_array($metadata) ? $metadata : [];
    }

    private function resolveWallet(StripeObject $session): ?Wallet
    {
        $metadata = $this->metadata($session);

        $walletId = $metadata['wallet_id'] ?? null;
        if (is_numeric($walletId)) {
            return Wallet::query()->find((int) $walletId);
        }

        $walletUuid = $metadata['wallet_uuid'] ?? null;
        if (is_string($walletUuid) && $walletUuid !== '') {
            return Wallet::query()->where('uuid', $walletUuid)->first();
        }

        $userIdentifier = $metadata['user_id']
            ?? $metadata['userId']
            ?? $session->client_reference_id
            ?? null;

        if ($userIdentifier === null || $userIdentifier === '') {
            return null;
        }

        $user = is_numeric($userIdentifier)
            ? User::query()->find((int) $userIdentifier)
            : User::query()->where('uuid', (string) $userIdentifier)->first();

        return $user?->wallet()->first();
    }

    private function amountFromSession(StripeObject $session): float
    {
        $amountTotal = $session->amount_total ?? null;

        if (! is_numeric($amountTotal)) {
            return 0.0;
        }

        return ((int) $amountTotal) / 100;
    }
}
