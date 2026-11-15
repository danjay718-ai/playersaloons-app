<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Wallet\Actions\SyncPayopDepositAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use RuntimeException;

class PayopWebhookController extends Controller
{
    public function __invoke(Request $request, SyncPayopDepositAction $sync): JsonResponse
    {
        $data = $request->validate(['invoice.id' => ['required', 'uuid'], 'transaction.order.id' => ['sometimes', 'string', 'max:255']]);
        try {
            $handled = $sync->execute($data['invoice']['id'], $data['transaction']['order']['id'] ?? null);
        } catch (LogicException|RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() === 422 ? 422 : 503);
        }

        return response()->json(['received' => true, 'handled' => $handled]);
    }
}
