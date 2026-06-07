<?php

declare(strict_types=1);

namespace App\Modules\Wallet\StateMachines;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\WithdrawalStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\StateMachines\AbstractStateMachine;

class WithdrawalStateMachine extends AbstractStateMachine
{
    /**
     * {@inheritDoc}
     *
     * Actual enum cases: PENDING, UNDER_REVIEW, APPROVED, REJECTED, PROCESSED
     * Transitions:
     *   PENDING      => [UNDER_REVIEW, REJECTED]  (admin picks up or rejects outright)
     *   UNDER_REVIEW => [APPROVED, REJECTED]       (review decision)
     *   APPROVED     => [PROCESSED]                (payment processed)
     *   REJECTED     => [PENDING]                  (retry / resubmit)
     *   PROCESSED    => []                         (terminal)
     */
    protected function transitions(): array
    {
        return [
            WithdrawalStatus::PENDING->value => [WithdrawalStatus::UNDER_REVIEW->value, WithdrawalStatus::REJECTED->value],
            WithdrawalStatus::UNDER_REVIEW->value => [WithdrawalStatus::APPROVED->value, WithdrawalStatus::REJECTED->value],
            WithdrawalStatus::APPROVED->value => [WithdrawalStatus::PROCESSED->value],
            WithdrawalStatus::REJECTED->value => [WithdrawalStatus::PENDING->value],
            WithdrawalStatus::PROCESSED->value => [],
        ];
    }

    /**
     * Guard: KYC must be approved before a withdrawal can be approved.
     *
     * @throws \LogicException
     */
    public function guardCanApprove(KycSubmission $kyc): void
    {
        if ($kyc->status !== KycStatus::APPROVED) {
            throw new \LogicException('KYC must be approved before a withdrawal can be approved.');
        }
    }

    /**
     * Guard: wallet must have sufficient balance to cover the withdrawal amount.
     *
     * @throws \LogicException
     */
    public function guardSufficientBalance(Wallet $wallet, float $amount): void
    {
        if ($wallet->cached_balance < $amount) {
            throw new \LogicException('Insufficient wallet balance for this withdrawal.');
        }
    }

    /**
     * Transition a withdrawal to a new status.
     *
     * @throws InvalidStateTransitionException
     * @throws \LogicException
     */
    public function transition(
        Withdrawal $withdrawal,
        WithdrawalStatus $to,
        ?KycSubmission $kyc = null,
        ?Wallet $wallet = null,
    ): void {
        $this->assertValidTransition($withdrawal->status, $to);

        if ($to === WithdrawalStatus::APPROVED) {
            $this->guardCanApprove($kyc);
            $this->guardSufficientBalance($wallet, (float) $withdrawal->amount);
        }

        $withdrawal->status = $to;
        $withdrawal->save();
    }
}
