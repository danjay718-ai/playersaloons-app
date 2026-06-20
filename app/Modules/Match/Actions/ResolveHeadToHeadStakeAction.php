<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use LogicException;

class ResolveHeadToHeadStakeAction
{
    public function __construct(private readonly WalletService $walletService) {}

    public function execute(HeadToHeadMatch $match, User $winner): LedgerEntry
    {
        $wallet = $winner->wallet;

        if (! $wallet) {
            throw new LogicException('Winner does not have a wallet.');
        }

        $existing = LedgerEntry::query()
            ->where('wallet_id', $wallet->getKey())
            ->where('reference_type', HeadToHeadMatch::class)
            ->where('reference_id', (string) $match->getKey())
            ->where('type', LedgerType::H2H_PAYOUT->value)
            ->first();

        if ($existing instanceof LedgerEntry) {
            return $existing;
        }

        $payout = number_format((float) $match->stake_amount * 2, 2, '.', '');

        return $this->walletService->credit(
            $wallet,
            $payout,
            LedgerType::H2H_PAYOUT,
            HeadToHeadMatch::class,
            (string) $match->getKey(),
            'Head-to-head match payout'
        );
    }
}
