<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Actions\ProcessDepositAction;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $webhookSecret = (string) config('services.stripe.webhook_secret', '');

        if ($webhookSecret === '') {
            Log::error('Stripe webhook secret is not configured.');

            return response()->json(['message' => 'Stripe webhook is not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (UnexpectedValueException) {
            return response()->json(['message' => 'Invalid Stripe webhook payload.'], 400);
        } catch (SignatureVerificationException) {
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

        $amount = $this->amountFromSession($session);
        if ($amount <= 0) {
            return response()->json(['message' => 'Stripe checkout session amount is invalid.'], 422);
        }

        $processDeposit->execute(
            $wallet,
            number_format($amount, 2, '.', ''),
            'stripe',
            (string) $session->id
        );

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
