<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\StateMachines\WithdrawalStateMachine;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ProcessWithdrawalAction
{
    public function __construct(private readonly WithdrawalStateMachine $stateMachine) {}

    /**
     * Mark a withdrawal as processed (payment has been disbursed externally).
     * Wallet debit is handled by CreateLedgerEntryListener on WithdrawalApproved.
     *
     * @throws AuthorizationException
     */
    public function execute(Withdrawal $withdrawal, User $operator): void
    {
        if (! $operator->hasAnyRole(['FINANCE_OPERATOR', 'ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only finance operators or admins may process withdrawals.');
        }

        DB::transaction(function () use ($withdrawal): void {
            $this->stateMachine->transition($withdrawal, WithdrawalStatus::PROCESSED);

            $withdrawal->update([
                'processed_at' => now(),
            ]);
        });
    }
}
