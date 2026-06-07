<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\StateMachines\WithdrawalStateMachine;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReviewWithdrawalAction
{
    public function __construct(private readonly WithdrawalStateMachine $stateMachine) {}

    /**
     * Move a withdrawal to UNDER_REVIEW.
     *
     * @throws AuthorizationException
     */
    public function execute(Withdrawal $withdrawal, User $reviewer): void
    {
        if (! $reviewer->hasAnyRole(['FINANCE_OPERATOR', 'ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only finance operators or admins may review withdrawals.');
        }

        // Enforce four-eyes policy
        if ((int) $withdrawal->getAttribute('user_id') === (int) $reviewer->getKey()) {
            throw new LogicException('Requestor cannot review their own withdrawal request.');
        }

        DB::transaction(function () use ($withdrawal, $reviewer): void {
            $this->stateMachine->transition($withdrawal, WithdrawalStatus::UNDER_REVIEW);

            $withdrawal->update([
                'reviewed_by' => $reviewer->getKey(),
            ]);
        });
    }
}
