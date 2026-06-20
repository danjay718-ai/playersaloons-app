<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use LogicException;

class LockHeadToHeadStakeAction
{
    public function __construct(private readonly WalletService $walletService) {}

    public function execute(User $user, string|float $amount, string $referenceType, string $referenceId): LedgerEntry
    {
        $wallet = $user->wallet;

        if (! $wallet) {
            throw new LogicException('User does not have a wallet.');
        }

        $existing = LedgerEntry::query()
            ->where('wallet_id', $wallet->getKey())
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('type', LedgerType::H2H_STAKE->value)
            ->first();

        if ($existing instanceof LedgerEntry) {
            return $existing;
        }

        return $this->walletService->debit(
            $wallet,
            $amount,
            LedgerType::H2H_STAKE,
            $referenceType,
            $referenceId,
            'Head-to-head stake locked'
        );
    }
}
