<?php

declare(strict_types=1);

namespace App\Modules\Team\Actions;

use App\Modules\Team\Models\TeamInvitation;
use App\Shared\Enums\TeamInvitationStatus;
use LogicException;

class RevokeTeamInvitationAction
{
    public function execute(TeamInvitation $invitation): void
    {
        if ($invitation->status !== TeamInvitationStatus::PENDING) {
            throw new LogicException('Only pending invitations can be revoked.');
        }

        $invitation->status = TeamInvitationStatus::REVOKED;
        $invitation->save();
    }
}
