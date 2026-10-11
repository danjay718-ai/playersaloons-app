<?php

declare(strict_types=1);

namespace App\Modules\Team\Listeners;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Team\Events\TeamMemberInvited;
use App\Modules\Team\Models\TeamInvitation;

class NotifyTeamInvitationListener
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(TeamMemberInvited $event): void
    {
        $invitation = TeamInvitation::query()->with(['team', 'invitee', 'inviter'])->find($event->invitationId);
        if (! $invitation?->team) {
            return;
        }

        $this->notifications->send(
            $invitation->invitee,
            'squad_invitation',
            __('Squad invitation'),
            __(':player invited you to join :squad. Open Squads to accept or decline.', [
                'player' => $invitation->inviter->username,
                'squad' => $invitation->team->name,
            ]),
            '/teams',
        );
    }
}
