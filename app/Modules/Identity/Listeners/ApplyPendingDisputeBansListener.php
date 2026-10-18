<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use App\Modules\Identity\Actions\ApplyPendingDisputeBanAction;
use App\Modules\Identity\Models\PlayerDisputeStrike;
use App\Modules\Match\Events\MatchCompleted;
use App\Modules\Match\Events\MatchForfeited;
use App\Modules\Tournament\Events\TournamentCancelled;
use App\Modules\Tournament\Events\TournamentCompleted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class ApplyPendingDisputeBansListener implements ShouldQueueAfterCommit
{
    public string $queue = 'tournament';

    public function handle(MatchCompleted|MatchForfeited|TournamentCompleted|TournamentCancelled $event): void
    {
        $tournamentId = $event->tournamentId;
        PlayerDisputeStrike::query()->where('tournament_id', $tournamentId)->where('ban_pending', true)
            ->pluck('id')->each(fn ($id) => app(ApplyPendingDisputeBanAction::class)->execute((int) $id));
    }
}
