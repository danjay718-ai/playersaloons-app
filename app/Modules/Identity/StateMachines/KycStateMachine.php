<?php

declare(strict_types=1);

namespace App\Modules\Identity\StateMachines;

use App\Modules\Identity\Models\KycSubmission;
use App\Shared\Enums\KycStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\StateMachines\AbstractStateMachine;

class KycStateMachine extends AbstractStateMachine
{
    /**
     * {@inheritDoc}
     *
     * Actual enum cases: NOT_SUBMITTED, SUBMITTED, UNDER_REVIEW, APPROVED, REJECTED
     * Transitions:
     *   NOT_SUBMITTED => [SUBMITTED]          (initial submission)
     *   SUBMITTED     => [UNDER_REVIEW]       (admin picks up for review)
     *   UNDER_REVIEW  => [APPROVED, REJECTED] (review decision)
     *   REJECTED      => [SUBMITTED]          (resubmit)
     *   APPROVED      => []                   (terminal)
     *
     * @return array<string, string[]>
     */
    protected function transitions(): array
    {
        return [
            KycStatus::NOT_SUBMITTED->value => [KycStatus::SUBMITTED->value],
            KycStatus::SUBMITTED->value => [KycStatus::UNDER_REVIEW->value],
            KycStatus::UNDER_REVIEW->value => [KycStatus::APPROVED->value, KycStatus::REJECTED->value],
            KycStatus::REJECTED->value => [KycStatus::SUBMITTED->value],
            KycStatus::APPROVED->value => [],
        ];
    }

    /**
     * Transition a KYC submission to a new status.
     *
     * @throws InvalidStateTransitionException
     */
    public function transition(KycSubmission $kyc, KycStatus $to): void
    {
        $from = $kyc->getAttribute('status');

        if (! $from instanceof KycStatus) {
            $from = KycStatus::from((string) $from);
        }

        $this->assertValidTransition($from, $to);

        $kyc->setAttribute('status', $to);
        $kyc->save();
    }
}
