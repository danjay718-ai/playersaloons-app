<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\Services\WalletService;
use App\Modules\Wallet\StateMachines\WithdrawalStateMachine;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\WithdrawalStatus;
use App\Shared\Support\Currency;
use Illuminate\Support\Facades\DB;
use LogicException;

class FailPayopPayoutAction
{
    /** Called only after a verified PayOp rejection or a definitive submission refusal. */
    public function execute(Withdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal): void {
            $locked = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->getKey());
            if ($locked->status === WithdrawalStatus::FAILED) {
                return;
            }
            if ($locked->payout_provider !== 'payop' || $locked->payout_submitted_at === null || $locked->payout_currency !== Currency::code()) {
                throw new LogicException('Only a submitted PayOp payout can be refunded.');
            }
            app(WithdrawalStateMachine::class)->transition($locked, WithdrawalStatus::FAILED);
            $ledger = app(WalletService::class)->credit(
                $locked->wallet, $locked->amount, LedgerType::REFUND, Withdrawal::class,
                (string) $locked->getKey(), 'PayOp rejected withdrawal refund', 'payop-payout-refund:'.$locked->uuid,
            );
            $locked->update(['payout_status' => 'rejected']);
            WalletCredited::dispatch((int) $locked->wallet_id, (int) $ledger->getKey(), $locked->amount, LedgerType::REFUND->value);
        });
    }
}
