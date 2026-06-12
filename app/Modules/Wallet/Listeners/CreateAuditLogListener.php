<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Listeners;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Events\WalletDebited;
use App\Modules\Wallet\Events\WithdrawalApproved;
use App\Modules\Wallet\Events\WithdrawalRejected;
use App\Modules\Wallet\Events\WithdrawalRequested;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class CreateAuditLogListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue the listener on the 'wallet' queue.
     */
    public string $queue = 'wallet';

    /**
     * Handle incoming domain events.
     */
    public function handle(object $event): void
    {
        if ($event instanceof WalletCredited) {
            $this->handleWalletCredited($event);
        } elseif ($event instanceof WalletDebited) {
            $this->handleWalletDebited($event);
        } elseif ($event instanceof WithdrawalRequested) {
            $this->handleWithdrawalRequested($event);
        } elseif ($event instanceof WithdrawalApproved) {
            $this->handleWithdrawalApproved($event);
        } elseif ($event instanceof WithdrawalRejected) {
            $this->handleWithdrawalRejected($event);
        }
    }

    private function handleWalletCredited(WalletCredited $event): void
    {
        $wallet = Wallet::query()->find($event->walletId);
        if (! $wallet) {
            return;
        }

        activity()
            ->performedOn($wallet)
            ->withProperties([
                'ledger_entry_id' => $event->ledgerEntryId,
                'amount' => $event->amount,
                'type' => $event->type,
            ])
            ->log('wallet_credited');
    }

    private function handleWalletDebited(WalletDebited $event): void
    {
        $wallet = Wallet::query()->find($event->walletId);
        if (! $wallet) {
            return;
        }

        activity()
            ->performedOn($wallet)
            ->withProperties([
                'ledger_entry_id' => $event->ledgerEntryId,
                'amount' => $event->amount,
                'type' => $event->type,
            ])
            ->log('wallet_debited');
    }

    private function handleWithdrawalRequested(WithdrawalRequested $event): void
    {
        $withdrawal = Withdrawal::query()->find($event->withdrawalId);
        if (! $withdrawal) {
            return;
        }

        $user = User::query()->find($event->userId);

        activity()
            ->causedBy($user)
            ->performedOn($withdrawal)
            ->withProperties([
                'wallet_id' => $event->walletId,
                'amount' => $event->amount,
            ])
            ->log('withdrawal_requested');
    }

    private function handleWithdrawalApproved(WithdrawalApproved $event): void
    {
        $withdrawal = Withdrawal::query()->find($event->withdrawalId);
        if (! $withdrawal) {
            return;
        }

        $user = User::query()->find($event->approvedBy);

        activity()
            ->causedBy($user)
            ->performedOn($withdrawal)
            ->log('withdrawal_approved');
    }

    private function handleWithdrawalRejected(WithdrawalRejected $event): void
    {
        $withdrawal = Withdrawal::query()->find($event->withdrawalId);
        if (! $withdrawal) {
            return;
        }

        $user = User::query()->find($event->rejectedBy);

        activity()
            ->causedBy($user)
            ->performedOn($withdrawal)
            ->withProperties([
                'reason' => $event->reason,
            ])
            ->log('withdrawal_rejected');
    }
}
