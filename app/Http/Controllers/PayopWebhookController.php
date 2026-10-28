<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Wallet\Actions\ProcessDepositAction;
use App\Modules\Wallet\Models\PaymentCheckout;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Support\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PayopWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessDepositAction $processDeposit): JsonResponse
    {
        $data = $request->validate(['invoice.id' => ['required', 'uuid']]);
        $invoiceId = $data['invoice']['id'];
        $checkout = PaymentCheckout::query()->where('provider', 'payop')->where('provider_reference', $invoiceId)->first();
        // Recover invoices created remotely when the checkout request timed out locally.
        if (! $checkout && is_string($request->input('transaction.order.id'))) {
            $checkout = PaymentCheckout::query()->where('provider', 'payop')->whereNull('provider_reference')
                ->where('id', $request->input('transaction.order.id'))->first();
        }
        if (! $checkout || $checkout->completed_at !== null) {
            return response()->json(['received' => true]);
        }

        // IPN payloads are untrusted; confirm payment directly with PayOp over HTTPS.
        $response = Http::acceptJson()->connectTimeout(5)->timeout(20)->get('https://api.payop.com/v1/invoices/'.$invoiceId);
        if (! $response->successful() || (int) $response->json('status') !== 1) {
            return response()->json(['message' => 'Payment verification unavailable.'], 503);
        }
        $invoice = $response->json('data');
        if (! is_array($invoice)
            || ($invoice['identifier'] ?? null) !== $invoiceId
            || ($invoice['orderIdentifier'] ?? null) !== $checkout->id
            || ($invoice['currency'] ?? null) !== $checkout->currency
            || $checkout->currency !== Currency::code()
            || ! is_numeric($invoice['amount'] ?? null)
            || (int) round((float) $invoice['amount'] * 100) !== (int) round((float) $checkout->total * 100)) {
            return response()->json(['message' => 'Payment verification mismatch.'], 422);
        }
        if ((int) ($invoice['status'] ?? -1) !== 1) {
            return response()->json(['received' => true, 'handled' => false]);
        }

        DB::transaction(function () use ($checkout, $processDeposit, $invoiceId): void {
            $locked = PaymentCheckout::query()->lockForUpdate()->findOrFail($checkout->id);
            if ($locked->completed_at !== null) {
                return;
            }
            abort_if($locked->provider_reference !== null && $locked->provider_reference !== $invoiceId, 422);
            $processDeposit->execute(Wallet::query()->findOrFail($locked->wallet_id), $locked->amount, 'payop', $invoiceId, $locked->fee_amount);
            $locked->update(['provider_reference' => $invoiceId, 'completed_at' => now()]);
        });

        return response()->json(['received' => true, 'handled' => true]);
    }
}
