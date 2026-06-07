<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserKycRejected;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\StateMachines\KycStateMachine;
use App\Shared\Enums\KycStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RejectKycAction
{
    public function __construct(private readonly KycStateMachine $kyc) {}

    /**
     * Reject KYC (UNDER_REVIEW -> REJECTED).
     *
     * @throws AuthorizationException
     */
    public function execute(KycSubmission $submission, User $reviewer, string $reason): void
    {
        if (! $reviewer->hasAnyRole(['KYC_REVIEWER', 'ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only KYC reviewers may reject submissions.');
        }

        DB::transaction(function () use ($submission, $reviewer, $reason): void {
            $submission->setAttribute('reviewed_by', $reviewer->getKey());
            $submission->setAttribute('reviewed_at', now());
            $submission->setAttribute('review_notes', $reason);

            $this->kyc->transition($submission, KycStatus::REJECTED);

            activity()
                ->causedBy($reviewer)
                ->performedOn($submission)
                ->withProperties(['reason' => $reason])
                ->log('kyc_rejected');

            UserKycRejected::dispatch(
                (int) $submission->getAttribute('user_id'),
                (int) $submission->getKey(),
                (int) $reviewer->getKey(),
                $reason
            );
        });
    }
}
