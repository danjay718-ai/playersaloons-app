<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Listeners;

use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Events\WalletDebited;
use App\Modules\Wallet\Events\WithdrawalApproved;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

class CreateLedgerEntryListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Queue the listener on the 'wallet' queue.
     */
    public string $queue = 'wallet';

    public function __construct(private WalletService $walletService) {}

    /**
     * Handle incoming domain events.
     */
    public function handle(object $event): void
    {
        if ($event instanceof WithdrawalApproved) {
            $this->handleWithdrawalApproved($event);
        } elseif ($event instanceof WalletCredited) {
            $this->handleWalletCredited($event);
        } elseif ($event instanceof WalletDebited) {
            $this->handleWalletDebited($event);
        }
    }

    private function handleWithdrawalApproved(WithdrawalApproved $event): void
    {
        $withdrawal = Withdrawal::query()->findOrFail($event->withdrawalId);

        // Idempotency: skip if not in APPROVED state or if debit ledger entry already exists
        if ($withdrawal->status !== WithdrawalStatus::APPROVED) {
            return;
        }

        $alreadyDebited = $withdrawal->wallet
            ->ledgerEntries()
            ->where('reference_type', Withdrawal::class)
            ->where('reference_id', (string) $withdrawal->getKey())
            ->exists();

        if ($alreadyDebited) {
            return;
        }

        $ledgerEntry = $this->walletService->debit(
            $withdrawal->wallet,
            $withdrawal->amount,
            LedgerType::WITHDRAWAL,
            Withdrawal::class,
            (string) $withdrawal->getKey(),
            'Withdrawal payout approved'
        );

        WalletDebited::dispatch(
            (int) $withdrawal->getAttribute('wallet_id'),
            (int) $ledgerEntry->getKey(),
            number_format((float) $withdrawal->getAttribute('amount'), 2, '.', ''),
            LedgerType::WITHDRAWAL->value
        );
    }

    private function handleWalletCredited(WalletCredited $event): void
    {
        // Check if transaction already exists (idempotency check)
        $exists = WalletTransaction::query()
            ->where('ledger_entry_id', $event->ledgerEntryId)
            ->exists();

        if ($exists) {
            return;
        }

        WalletTransaction::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $event->walletId,
            'ledger_entry_id' => $event->ledgerEntryId,
            'type' => $event->type,
            'status' => 'completed',
            'amount' => $event->amount,
            'metadata_json' => null,
        ]);
    }

    private function handleWalletDebited(WalletDebited $event): void
    {
        // Check if transaction already exists (idempotency check)
        $exists = WalletTransaction::query()
            ->where('ledger_entry_id', $event->ledgerEntryId)
            ->exists();

        if ($exists) {
            return;
        }

        WalletTransaction::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $event->walletId,
            'ledger_entry_id' => $event->ledgerEntryId,
            'type' => $event->type,
            'status' => 'completed',
            'amount' => $event->amount,
            'metadata_json' => null,
        ]);
    }
}
