<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerEntryResource;
use App\Http\Resources\WalletResource;
use App\Http\Resources\WithdrawalResource;
use App\Modules\Wallet\Actions\RequestWithdrawalAction;
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use LogicException;

class WalletApiController extends Controller
{
    /**
     * Get the authenticated user's wallet balance.
     */
    public function balance(Request $request): WalletResource
    {
        $wallet = $request->user()->wallet;

        if (! $wallet) {
            abort(404, 'Wallet not found.');
        }

        Gate::authorize('view', $wallet);

        return new WalletResource($wallet);
    }

    /**
     * Get the authenticated user's wallet transaction ledger entries.
     */
    public function transactions(Request $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        $wallet = $request->user()->wallet;

        if (! $wallet) {
            abort(404, 'Wallet not found.');
        }

        Gate::authorize('view', $wallet);

        $perPage = (int) $request->query('per_page', '15');
        $ledgerEntries = $wallet->ledgerEntries()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return LedgerEntryResource::collection($ledgerEntries);
    }

    /**
     * Request a withdrawal from the user's wallet.
     */
    public function withdraw(
        Request $request,
        RequestWithdrawalAction $action
    ): JsonResponse {
        $wallet = $request->user()->wallet;

        if (! $wallet) {
            return response()->json(['message' => 'User does not have a wallet.'], 404);
        }

        Gate::authorize('requestWithdrawal', $wallet);

        $amount = $request->input('amount');
        if ($amount === null) {
            return response()->json(['message' => 'The amount field is required.'], 422);
        }

        try {
            $withdrawal = $action->execute($request->user(), $amount);

            return (new WithdrawalResource($withdrawal))
                ->additional(['message' => 'Withdrawal request submitted successfully.'])
                ->response()
                ->setStatusCode(201);
        } catch (InvalidArgumentException|InsufficientBalanceException|\App\Shared\Exceptions\InvalidStateTransitionException|LogicException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
