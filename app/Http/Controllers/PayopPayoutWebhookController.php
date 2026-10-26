<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Wallet\Actions\SyncPayopPayoutAction;
use App\Modules\Wallet\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayopPayoutWebhookController extends Controller
{
    public function __invoke(Request $request, SyncPayopPayoutAction $sync): JsonResponse
    {
        $data = $request->validate([
            'transaction.withdrawalId' => ['required', 'uuid'],
            'transaction.metadata.internalWithdrawalId' => ['nullable', 'uuid'],
        ]);
        $reference = $data['transaction']['withdrawalId'];
        $uuid = $data['transaction']['metadata']['internalWithdrawalId'] ?? null;
        $exists = Withdrawal::query()->where('payout_provider', 'payop')->whereNotNull('payout_submitted_at')
            ->where(function ($query) use ($reference, $uuid): void {
                $query->where('payout_reference', $reference);
                if ($uuid !== null) {
                    $query->orWhere(fn ($query) => $query->whereNull('payout_reference')->where('uuid', $uuid));
                }
            })->exists();
        if ($exists) {
            $sync->execute($reference);
        }

        return response()->json(['received' => true]);
    }
}
