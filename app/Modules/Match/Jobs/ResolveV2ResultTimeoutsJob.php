<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Actions\SubmitV2MatchResultAction;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchAttempt;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

final class ResolveV2ResultTimeoutsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(SubmitV2MatchResultAction $results): void
    {
        if (! config('features.tournament_v2.enabled')) {
            return;
        }

        MatchAttempt::query()
            ->where('status', 'open')
            ->whereNotNull('result_deadline_at')
            ->where('result_deadline_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $attemptId) use ($results): void {
                DB::transaction(function () use ($attemptId, $results): void {
                    $matchId = MatchAttempt::query()->whereKey($attemptId)->value('match_id');
                    if ($matchId === null) {
                        return;
                    }
                    $tournamentId = GameMatch::query()->whereKey($matchId)->value('tournament_id');
                    Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
                    $match = GameMatch::query()->lockForUpdate()->findOrFail($matchId);
                    $attempt = MatchAttempt::query()->lockForUpdate()->find($attemptId);
                    if ($attempt === null || $attempt->status !== 'open' || $attempt->result_deadline_at?->isFuture()) {
                        return;
                    }
                    $first = $attempt->submissions()->orderBy('id')->first();
                    if ($first === null || $attempt->submissions()->count() !== 1) {
                        return;
                    }

                    // Confirmed client rule: the first submitter wins when the opponent does not respond,
                    // regardless of the outcome selected in that first submission.
                    $results->complete($match, $attempt, (int) $first->registration_id, 'opponent_submission_timeout');
                }, 3);
            });
    }
}
