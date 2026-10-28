<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Events\WalletDebited;
use App\Modules\Wallet\Events\WithdrawalApproved;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\Services\WalletService;
use App\Modules\Wallet\StateMachines\WithdrawalStateMachine;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class ApproveWithdrawalAction
{
    public function __construct(private readonly WithdrawalStateMachine $stateMachine) {}

    /**
     * Approve a withdrawal.
     *
     * @throws AuthorizationException
     */
    public function execute(Withdrawal $withdrawal, User $reviewer, ?string $notes = null): void
    {
        if (! $reviewer->hasAnyRole(['FINANCE_OPERATOR', 'ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only finance operators or admins may approve withdrawals.');
        }

        // Enforce four-eyes policy
        if ((int) $withdrawal->getAttribute('user_id') === (int) $reviewer->getKey()) {
            throw new LogicException('Requestor cannot approve their own withdrawal request.');
        }

        DB::transaction(function () use ($withdrawal, $reviewer, $notes): void {
            $withdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->getKey());
            $kyc = KycSubmission::query()
                ->where('user_id', $withdrawal->getAttribute('user_id'))
                ->where('status', KycStatus::APPROVED)
                ->first();

            if (! $kyc instanceof KycSubmission) {
                throw new LogicException('User does not have an approved KYC submission.');
            }

            $wallet = Wallet::query()->lockForUpdate()->find($withdrawal->wallet_id);
            if (! $wallet) {
                throw new LogicException('User does not have a wallet.');
            }

            // Transition using the state machine, which triggers guards
            $this->stateMachine->transition($withdrawal, WithdrawalStatus::APPROVED, $kyc, $wallet);

            $withdrawal->update([
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            if ($withdrawal->payout_provider === 'payop') {
                // Reserve funds before an external payout, independently of queue timing.
                $entry = app(WalletService::class)->debit($wallet, $withdrawal->amount, LedgerType::WITHDRAWAL,
                    Withdrawal::class, (string) $withdrawal->getKey(), 'Withdrawal payout approved', 'payop-payout-debit:'.$withdrawal->uuid);
                WalletDebited::dispatch((int) $wallet->getKey(), (int) $entry->getKey(), $withdrawal->amount, LedgerType::WITHDRAWAL->value);
            }

            WithdrawalApproved::dispatch(
                (int) $withdrawal->getKey(),
                (int) $wallet->getKey(),
                (int) $reviewer->getKey()
            );
        });
        $withdrawal->refresh();
    }
}
