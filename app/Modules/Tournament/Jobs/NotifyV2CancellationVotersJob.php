<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Jobs;

use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Models\User;
use App\Modules\Tournament\Models\TournamentCancellationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class NotifyV2CancellationVotersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $requestId)
    {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notifications): void
    {
        $request = TournamentCancellationRequest::query()->with('tournament')->find($this->requestId);
        if ($request === null || $request->tournament === null) {
            return;
        }

        $tournament = $request->tournament;
        User::query()
            ->with('notificationPreference')
            ->whereIn('id', array_map('intval', $request->eligible_voter_ids ?? []))
            ->eachById(fn (User $voter) => $notifications->send(
                $voter,
                'tournament_cancellation_vote',
                'Cancellation vote requested',
                "A player requested to cancel their entry in {$tournament->name}. Vote before tournament start.",
                "/tournaments/{$tournament->uuid}/view",
            ));
    }
}
