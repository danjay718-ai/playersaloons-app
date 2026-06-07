<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Events\WithdrawalRejected;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\StateMachines\WithdrawalStateMachine;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class RejectWithdrawalAction
{
    public function __construct(private readonly WithdrawalStateMachine $stateMachine) {}

    /**
     * Reject a withdrawal request.
     *
     * @param Withdrawal $withdrawal
     * @param User $reviewer
     * @param string $reason
     * @return void
     * @throws AuthorizationException
     */
    public function execute(Withdrawal $withdrawal, User $reviewer, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A rejection reason is required.');
        }

        if (! $reviewer->hasAnyRole(['FINANCE_OPERATOR', 'ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only finance operators or admins may reject withdrawals.');
        }

        // Enforce four-eyes policy
        if ((int) $withdrawal->getAttribute('user_id') === (int) $reviewer->getKey()) {
            throw new LogicException('Requestor cannot reject their own withdrawal request.');
        }

        DB::transaction(function () use ($withdrawal, $reviewer, $reason): void {
            $this->stateMachine->transition($withdrawal, WithdrawalStatus::REJECTED);

            $withdrawal->update([
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_notes' => $reason,
            ]);

            WithdrawalRejected::dispatch(
                (int) $withdrawal->getKey(),
                (int) $withdrawal->getAttribute('wallet_id'),
                (int) $reviewer->getKey(),
                $reason
            );
        });
    }
}
