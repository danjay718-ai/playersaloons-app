<?php

declare(strict_types=1);

namespace App\Modules\Team\Events;

use App\Shared\Events\DomainEvent;

final class TeamMemberInvited extends DomainEvent
{
    public function __construct(
        public readonly int $invitationId,
        public readonly int $teamId,
        public readonly int $invitedUserId,
        public readonly int $invitedByUserId,
    ) {
        parent::__construct();
    }
}
