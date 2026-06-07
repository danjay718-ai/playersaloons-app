<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\StateMachines\KycStateMachine;
use App\Shared\Enums\KycStatus;

class ReviewKycAction
{
    public function __construct(private readonly KycStateMachine $kyc) {}

    /** Move to UNDER_REVIEW (SUBMITTED -> UNDER_REVIEW). */
    public function execute(KycSubmission $submission): void
    {
        $this->kyc->transition($submission, KycStatus::UNDER_REVIEW);
    }
}
