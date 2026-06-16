<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Services;

use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Modules\Wallet\Exceptions\WalletFrozenException;
use App\Modules\Wallet\Exceptions\WalletSuspendedException;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\WalletStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    /**
     * Credit a wallet with an amount.
     *
     * @param  string|float  $amount
     *
     * @throws WalletFrozenException
     */
    public function credit(
        Wallet $wallet,
        $amount,
        LedgerType $type,
        string $referenceType,
        string $referenceId,
        ?string $description = null
    ): LedgerEntry {
        return DB::transaction(function () use ($wallet, $amount, $type, $referenceType, $referenceId, $description): LedgerEntry {
            /** @var Wallet|null $lockedWallet */
            $lockedWallet = Wallet::query()->where('id', $wallet->getKey())->lockForUpdate()->first();
            if ($lockedWallet === null) {
                throw new ModelNotFoundException;
            }
            $wallet = $lockedWallet;

            if ($wallet->status === WalletStatus::FROZEN) {
                throw new WalletFrozenException($wallet->getAttribute('uuid'));
            }

            $amountDecimal = number_format(abs((float) $amount), 2, '.', '');
            $currentBalance = $wallet->getAttribute('cached_balance') ?? '0.00';
            $newBalance = number_format((float) $currentBalance + (float) $amountDecimal, 2, '.', '');

            $ledgerEntry = LedgerEntry::query()->create([
                'uuid' => Str::uuid()->toString(),
                'wallet_id' => $wallet->getKey(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'type' => $type,
                'amount' => $amountDecimal,
                'running_balance' => $newBalance,
                'description' => $description,
                'created_at' => now(),
            ]);

            $wallet->update([
                'cached_balance' => $newBalance,
            ]);

            return $ledgerEntry;
        });
    }

    /**
     * Debit a wallet with an amount.
     *
     * @param  string|float  $amount
     *
     * @throws WalletFrozenException
     * @throws WalletSuspendedException
     * @throws InsufficientBalanceException
     */
    public function debit(
        Wallet $wallet,
        $amount,
        LedgerType $type,
        string $referenceType,
        string $referenceId,
        ?string $description = null
    ): LedgerEntry {
        return DB::transaction(function () use ($wallet, $amount, $type, $referenceType, $referenceId, $description): LedgerEntry {
            /** @var Wallet|null $lockedWallet */
            $lockedWallet = Wallet::query()->where('id', $wallet->getKey())->lockForUpdate()->first();
            if ($lockedWallet === null) {
                throw new ModelNotFoundException;
            }
            $wallet = $lockedWallet;

            if ($wallet->status === WalletStatus::FROZEN) {
                throw new WalletFrozenException($wallet->getAttribute('uuid'));
            }

            if ($wallet->status === WalletStatus::SUSPENDED) {
                throw new WalletSuspendedException($wallet->getAttribute('uuid'));
            }

            $amountDecimal = number_format(abs((float) $amount), 2, '.', '');
            $currentBalance = $wallet->getAttribute('cached_balance') ?? '0.00';

            if ((float) $currentBalance < (float) $amountDecimal) {
                throw new InsufficientBalanceException($wallet->getAttribute('uuid'), $amountDecimal, (string) $currentBalance);
            }

            $newBalance = number_format((float) $currentBalance - (float) $amountDecimal, 2, '.', '');

            // Store debit amount as negative in ledger entries
            $negativeAmount = number_format(-((float) $amountDecimal), 2, '.', '');

            $ledgerEntry = LedgerEntry::query()->create([
                'uuid' => Str::uuid()->toString(),
                'wallet_id' => $wallet->getKey(),
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'type' => $type,
                'amount' => $negativeAmount,
                'running_balance' => $newBalance,
                'description' => $description,
                'created_at' => now(),
            ]);

            $wallet->update([
                'cached_balance' => $newBalance,
            ]);

            return $ledgerEntry;
        });
    }

    /**
     * Get the cached balance from DB.
     */
    public function getBalance(Wallet $wallet): string
    {
        return number_format((float) $wallet->getAttribute('cached_balance'), 2, '.', '');
    }

    /**
     * Recalculate balance from ledger entries and fix drift.
     */
    public function recalculateBalance(Wallet $wallet): string
    {
        return DB::transaction(function () use ($wallet): string {
            /** @var Wallet|null $lockedWallet */
            $lockedWallet = Wallet::query()->where('id', $wallet->getKey())->lockForUpdate()->first();
            if ($lockedWallet === null) {
                throw new ModelNotFoundException;
            }
            $wallet = $lockedWallet;

            // Compute actual balance by summing the ledger entries
            // Since credits are stored as positive and debits as negative, we can simply sum them
            $sum = LedgerEntry::query()
                ->where('wallet_id', $wallet->getKey())
                ->sum('amount');

            $recalculated = number_format((float) $sum, 2, '.', '');

            $wallet->update([
                'cached_balance' => $recalculated,
            ]);

            return $recalculated;
        });
    }
}
