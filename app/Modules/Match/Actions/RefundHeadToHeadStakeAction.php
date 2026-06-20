<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use LogicException;

class RefundHeadToHeadStakeAction
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
            ->where('type', LedgerType::REFUND->value)
            ->first();

        if ($existing instanceof LedgerEntry) {
            return $existing;
        }

        return $this->walletService->credit(
            $wallet,
            $amount,
            LedgerType::REFUND,
            $referenceType,
            $referenceId,
            'Head-to-head stake refunded'
        );
    }
}
