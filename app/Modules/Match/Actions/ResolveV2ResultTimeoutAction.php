<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchAttempt;
use App\Modules\Tournament\Models\Tournament;
use Illuminate\Support\Facades\DB;

final readonly class ResolveV2ResultTimeoutAction
{
    public function __construct(private SubmitV2MatchResultAction $results) {}

    public function execute(int $attemptId): bool
    {
        return DB::transaction(function () use ($attemptId): bool {
            $matchId = MatchAttempt::query()->whereKey($attemptId)->value('match_id');
            if ($matchId === null) {
                return false;
            }

            $tournamentId = GameMatch::query()->whereKey($matchId)->value('tournament_id');
            if ($tournamentId === null) {
                return false;
            }

            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $match = GameMatch::query()->lockForUpdate()->findOrFail($matchId);
            $attempt = MatchAttempt::query()->lockForUpdate()->find($attemptId);

            if ($attempt === null || $attempt->status !== 'open' || $attempt->result_deadline_at?->isFuture()) {
                return false;
            }

            $submissions = $attempt->submissions()->orderBy('id')->get();
            if ($submissions->count() !== 1) {
                return false;
            }

            $this->results->complete(
                $match,
                $attempt,
                (int) $submissions->first()->registration_id,
                'opponent_submission_timeout',
            );

            return true;
        }, 3);
    }
}
