<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Modules\Tournament\Services\CancelledOccurrenceRetention;
use App\Shared\Enums\PaymentStatus;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes truly empty occurrences and archives free underfilled
 * cancellations after the recurrence retention boundary. Protected history is
 * never a cleanup candidate.
 */
final class PurgeEmptyV2OccurrencesAction
{
    /** @var list<string> */
    private const PROTECTED_TABLES = [
        'tournament_participants',
        'brackets',
        'matches',
        'prize_distributions',
        'refunds',
        'tournament_cancellation_requests',
        'player_dispute_strikes',
        'tournament_rules',
        'tournament_announcements',
        'stream_channels',
        'tournament_teams',
        'tournament_team_members',
        'tournament_team_search_entries',
    ];

    public function __construct(private readonly CancelledOccurrenceRetention $retention) {}

    public function execute(): int
    {
        $cleaned = 0;

        Tournament::query()
            ->where('workflow_version', 2)
            ->where(function ($query): void {
                $query->where('status', TournamentStatus::CANCELLED)
                    ->orWhere('status', TournamentStatus::REFUNDED);
            })
            ->whereNotNull('end_at')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($occurrences) use (&$cleaned): void {
                foreach ($occurrences as $occurrence) {
                    $cleaned += DB::transaction(function () use ($occurrence): int {
                        $locked = Tournament::query()->lockForUpdate()->find($occurrence->id);
                        if ($locked === null || ! $this->retention->isEligible($locked)) {
                            return 0;
                        }

                        $registrations = $locked->registrations()->get(['id', 'status', 'payment_status']);
                        $hasProtectedHistory = $this->hasProtectedHistory($locked)
                            || $registrations->contains(fn (TournamentRegistration $registration): bool => $registration->payment_status !== PaymentStatus::FREE);
                        if ($registrations->isEmpty() && ! $hasProtectedHistory && $this->hasOnlyEmptyCancellation($locked)) {
                            $locked->forceDelete();

                            return 1;
                        }

                        // At the retention boundary, hide every terminal cancellation
                        // from normal lists. Soft deletion preserves paid/refund/match
                        // history for finance and audit recovery.
                        if ($hasProtectedHistory) {
                            $locked->delete();

                            return 1;
                        }

                        $archiveable = (float) $locked->entry_fee === 0.0
                            && $registrations->isNotEmpty()
                            && $registrations->every(fn (TournamentRegistration $registration): bool => $registration->status === RegistrationStatus::CANCELLED
                                && $registration->payment_status === PaymentStatus::FREE);
                        if (! $archiveable) {
                            return 0;
                        }

                        $locked->delete();

                        return 1;
                    }, 3);
                }
            });

        return $cleaned;
    }

    private function hasProtectedHistory(Tournament $tournament): bool
    {
        foreach (self::PROTECTED_TABLES as $table) {
            if (DB::table($table)->where('tournament_id', $tournament->id)->exists()) {
                return true;
            }
        }

        return DB::table('ledger_entries')
            ->where('reference_type', Tournament::class)
            ->where('reference_id', (string) $tournament->id)
            ->exists();
    }

    private function hasOnlyEmptyCancellation(Tournament $tournament): bool
    {
        $cancellation = DB::table('tournament_cancellations')->where('tournament_id', $tournament->id)->first();

        return $cancellation === null
            || ((int) $cancellation->affected_participant_count === 0 && ! $cancellation->refund_required);
    }
}
