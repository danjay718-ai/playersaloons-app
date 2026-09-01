<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;

/**
 * Removes only expired V2 occurrences that never acquired participant, match,
 * financial, or operational data. Historical/player-bearing occurrences remain
 * immutable records and are never candidates for this cleanup.
 */
final class PurgeEmptyV2OccurrencesAction
{
    /** @var list<string> */
    private const PROTECTED_TABLES = [
        'tournament_registrations',
        'tournament_participants',
        'brackets',
        'matches',
        'prize_distributions',
        'refunds',
        'tournament_cancellation_requests',
        'tournament_rules',
        'tournament_announcements',
        'stream_channels',
        'tournament_teams',
        'tournament_team_members',
        'tournament_team_search_entries',
    ];

    public function execute(): int
    {
        $purged = 0;

        Tournament::query()
            ->where('workflow_version', 2)
            ->where('status', TournamentStatus::CANCELLED)
            ->whereNotNull('end_at')
            ->where('end_at', '<=', now())
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($occurrences) use (&$purged): void {
                foreach ($occurrences as $occurrence) {
                    $purged += DB::transaction(function () use ($occurrence): int {
                        $locked = Tournament::query()->lockForUpdate()->find($occurrence->id);
                        if ($locked === null || ! $this->isPurgeable($locked)) {
                            return 0;
                        }

                        // Empty cancellation rows are intentionally removed by the FK cascade.
                        $locked->forceDelete();

                        return 1;
                    }, 3);
                }
            });

        return $purged;
    }

    private function isPurgeable(Tournament $tournament): bool
    {
        foreach (self::PROTECTED_TABLES as $table) {
            if (DB::table($table)->where('tournament_id', $tournament->id)->exists()) {
                return false;
            }
        }

        $cancellation = DB::table('tournament_cancellations')->where('tournament_id', $tournament->id)->first();

        return $cancellation === null
            || ((int) $cancellation->affected_participant_count === 0 && ! $cancellation->refund_required);
    }
}
