<?php

declare(strict_types=1);

namespace App\Modules\Match\Listeners;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Models\GameMatch;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class NotifyAdminsOfDisputeListener implements ShouldQueueAfterCommit
{
    public string $queue = 'mail';

    public function __construct(private readonly NotificationService $notifications) {}

    public function handle(MatchDisputed $event): void
    {
        $match = GameMatch::query()->with('tournament:id,name')->find($event->matchId);
        if ($match === null) {
            return;
        }

        User::query()->role(['ADMIN', 'SUPER_ADMIN'])->each(function (User $admin) use ($match): void {
            $this->notifications->send(
                $admin,
                'admin_match_dispute',
                'Match dispute requires review',
                "A result conflict in {$match->tournament->name} requires an administrator ruling.",
                '/admin/matches?filter=disputes',
            );
        });
    }
}
