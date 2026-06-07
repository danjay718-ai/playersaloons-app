<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Shared\Events\DomainEvent;

final class UserKycSubmitted extends DomainEvent
{
    public function __construct(
        public readonly int $userId,
        public readonly int $kycSubmissionId,
    ) {
        parent::__construct();
    }
}
