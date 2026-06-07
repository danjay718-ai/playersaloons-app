<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Events\UserKycApproved;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\StateMachines\KycStateMachine;
use App\Shared\Enums\KycStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ApproveKycAction
{
    public function __construct(private readonly KycStateMachine $kyc) {}

    /**
     * Approve KYC (UNDER_REVIEW -> APPROVED).
     *
     * @throws AuthorizationException
     */
    public function execute(KycSubmission $submission, User $reviewer): void
    {
        if (! $reviewer->hasAnyRole(['KYC_REVIEWER', 'ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only KYC reviewers may approve submissions.');
        }

        DB::transaction(function () use ($submission, $reviewer): void {
            $submission->setAttribute('reviewed_by', $reviewer->getKey());
            $submission->setAttribute('reviewed_at', now());

            $this->kyc->transition($submission, KycStatus::APPROVED);

            activity()
                ->causedBy($reviewer)
                ->performedOn($submission)
                ->log('kyc_approved');

            UserKycApproved::dispatch(
                (int) $submission->getAttribute('user_id'),
                (int) $submission->getKey(),
                (int) $reviewer->getKey()
            );
        });
    }
}
