<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Models\Deposit;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProcessDepositAction
{
    public function __construct(private readonly WalletService $walletService) {}

    /**
     * Process a completed deposit.
     *
     * @param  string|float|float  $amount
     */
    public function execute(Wallet $wallet, $amount, string $provider, string $providerReference): Deposit
    {
        if ((float) $amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($wallet, $amount, $provider, $providerReference): Deposit {
            // Check for duplicate reference (idempotency check)
            $existing = Deposit::query()
                ->where('provider', $provider)
                ->where('provider_reference', $providerReference)
                ->first();

            if ($existing instanceof Deposit) {
                return $existing;
            }

            $deposit = Deposit::query()->create([
                'uuid' => Str::uuid()->toString(),
                'wallet_id' => $wallet->getKey(),
                'amount' => $amount,
                'provider' => $provider,
                'provider_reference' => $providerReference,
                'status' => 'completed',
            ]);

            $ledgerEntry = $this->walletService->credit(
                $wallet,
                $amount,
                LedgerType::DEPOSIT,
                Deposit::class,
                (string) $deposit->getKey(),
                "Deposit via {$provider}"
            );

            WalletCredited::dispatch(
                (int) $wallet->getKey(),
                (int) $ledgerEntry->getKey(),
                number_format((float) $amount, 2, '.', ''),
                LedgerType::DEPOSIT->value
            );

            return $deposit;
        });
    }
}
