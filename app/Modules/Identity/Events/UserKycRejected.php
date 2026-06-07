<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Shared\Events\DomainEvent;

final class UserKycRejected extends DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly int $kycSubmissionId,
        public readonly int $reviewedBy,
        public readonly string $reason,
    ) {
        parent::__construct();
    }
}
