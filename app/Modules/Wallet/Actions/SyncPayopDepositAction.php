<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Models\PaymentCheckout;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Support\Currency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class SyncPayopDepositAction
{
    public function execute(string $invoiceId, ?string $checkoutId = null): bool
    {
        $checkout = PaymentCheckout::query()->where('provider', 'payop')->where('provider_reference', $invoiceId)->first();
        if (! $checkout && $checkoutId !== null) {
            $checkout = PaymentCheckout::query()->where('provider', 'payop')->whereNull('provider_reference')->find($checkoutId);
        }
        if (! $checkout) {
            return false;
        }
        if ($checkout->completed_at !== null) {
            return true;
        }
        $response = Http::acceptJson()->connectTimeout(5)->timeout(20)->get('https://api.payop.com/v1/invoices/'.$invoiceId);
        if ($response->status() === 422 && $response->json('message') === 'Invoice is overdue' && $checkout->provider_reference === $invoiceId) {
            // Expired invoice details can be unavailable after payment; only use an invoice bound at creation.
            $status = Http::acceptJson()->connectTimeout(5)->timeout(20)->get('https://api.payop.com/v1/checkout/check-invoice-status/'.$invoiceId);
            if (! $status->successful() || (int) $status->json('status') !== 1) {
                throw new RuntimeException('Payment verification unavailable.', 503);
            }
            if ($status->json('data.isSuccess') !== true || $status->json('data.status') !== 'success') {
                if (in_array($status->json('data.status'), ['failed', 'expired'], true)) {
                    $checkout->update(['status' => (string) $status->json('data.status'), 'failed_at' => now()]);
                }

                return false;
            }
        } else {
            if (! $response->successful() || (int) $response->json('status') !== 1) {
                throw new RuntimeException('Payment verification unavailable.', 503);
            }
            $invoice = $response->json('data');
            if (! is_array($invoice)
                || ($invoice['identifier'] ?? null) !== $invoiceId
                || ($invoice['orderIdentifier'] ?? null) !== $checkout->id
                || ($invoice['currency'] ?? null) !== $checkout->currency
                || ! is_numeric($invoice['amount'] ?? null)
                || (int) round((float) $invoice['amount'] * 100) !== (int) round((float) $checkout->total * 100)) {
                throw new LogicException('Payment verification mismatch.', 422);
            }
            if ((int) ($invoice['status'] ?? -1) !== 1) {
                if ((int) ($invoice['status'] ?? -1) === 5) {
                    $checkout->update(['status' => 'failed', 'failed_at' => now()]);
                }

                return false;
            }
        }
        if ($checkout->currency !== Currency::code()) {
            throw new LogicException('Payment verification mismatch.', 422);
        }
        DB::transaction(function () use ($checkout, $invoiceId): void {
            $locked = PaymentCheckout::query()->lockForUpdate()->findOrFail($checkout->id);
            if ($locked->completed_at !== null) {
                return;
            }
            if ($locked->provider_reference !== null && $locked->provider_reference !== $invoiceId) {
                throw new LogicException('Payment verification mismatch.', 422);
            }
            app(ProcessDepositAction::class)->execute(Wallet::query()->findOrFail($locked->wallet_id), $locked->amount, 'payop', $invoiceId, $locked->fee_amount);
            $locked->update(['provider_reference' => $invoiceId, 'status' => 'completed', 'completed_at' => now()]);
        });

        return true;
    }
}
