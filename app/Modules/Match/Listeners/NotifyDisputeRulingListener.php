<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Match\Events\MatchDisputeResolved;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\Support\DisputeRulingMessage;
use App\Shared\Enums\DisputeResolution;

final class NotifyDisputeRulingListener
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(MatchDisputeResolved $event): void
    {
        $dispute = MatchDispute::query()->with([
            'match.playerARegistration.user.notificationPreference',
            'match.playerARegistration.team',
            'match.playerARegistration.rosterMembers.user.notificationPreference',
            'match.playerBRegistration.user.notificationPreference',
            'match.playerBRegistration.team',
            'match.playerBRegistration.rosterMembers.user.notificationPreference',
        ])->findOrFail($event->disputeId);
        $match = GameMatch::query()->with('tournament')->findOrFail($event->playableMatchId);
        $url = (int) $match->tournament->workflow_version === 2
            ? "/tournaments/{$match->tournament->uuid}/view?activeTab=submit-results&match={$match->uuid}"
            : "/matches/{$match->uuid}";
        $isRematch = in_array($dispute->resolution, [DisputeResolution::REMATCH, DisputeResolution::DRAW], true);

        // Persist the ruling after commit without depending on a queue worker.
        // NotificationService still queues email delivery and respects preferences.
        $recipients = collect([$dispute->match->playerARegistration, $dispute->match->playerBRegistration])
            ->filter()
            ->flatMap(fn ($registration) => collect([$registration->user])->merge($registration->rosterMembers->pluck('user')))
            ->filter()
            ->unique('id');

        foreach ($recipients as $recipient) {
            $this->notifications->send(
                $recipient,
                $isRematch ? 'match_rematch' : 'match_completed',
                $isRematch ? __('Rematch Required') : __('Admin Ruling'),
                DisputeRulingMessage::for($dispute),
                $url,
            );
        }
    }
}
