<?php

declare(strict_types=1);

namespace App\Modules\Match\Jobs;

use App\Modules\Match\Actions\RefundHeadToHeadStakeAction;
use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Match\StateMachines\HeadToHeadMatchStateMachine;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use App\Shared\Enums\HeadToHeadMatchStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ExpireHeadToHeadMatchesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const DEFAULT_MATCH_TIMER_MINUTES = 30;

    private const IN_PROGRESS_GRACE_MINUTES = 15;

    public function handle(
        RefundHeadToHeadStakeAction $refundStake,
        HeadToHeadMatchStateMachine $matchStateMachine
    ): void {
        $this->expireWaitingChallenges($refundStake);
        $this->escalateStaleInProgressMatches($matchStateMachine);
        $this->escalateStaleSubmittedResults($matchStateMachine);
    }

    private function expireWaitingChallenges(RefundHeadToHeadStakeAction $refundStake): void
    {
        HeadToHeadChallenge::query()
            ->with('creator')
            ->where('status', HeadToHeadChallengeStatus::WAITING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($challenges) use ($refundStake): void {
                foreach ($challenges as $challenge) {
                    DB::transaction(function () use ($challenge, $refundStake): void {
                        /** @var HeadToHeadChallenge $lockedChallenge */
                        $lockedChallenge = HeadToHeadChallenge::query()
                            ->where('id', $challenge->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        if ($lockedChallenge->status !== HeadToHeadChallengeStatus::WAITING) {
                            return;
                        }

                        $lockedChallenge->status = HeadToHeadChallengeStatus::EXPIRED;
                        $lockedChallenge->cancelled_at = now();
                        $lockedChallenge->save();

                        $creator = $lockedChallenge->creator;
                        if ($creator) {
                            $refundStake->execute(
                                $creator,
                                (string) $lockedChallenge->stake_amount,
                                HeadToHeadChallenge::class,
                                (string) $lockedChallenge->getKey()
                            );
                        }
                    });
                }
            });
    }

    private function escalateStaleInProgressMatches(HeadToHeadMatchStateMachine $matchStateMachine): void
    {
        HeadToHeadMatch::query()
            ->where('status', HeadToHeadMatchStatus::IN_PROGRESS)
            ->whereNotNull('started_at')
            ->chunkById(100, function ($matches) use ($matchStateMachine): void {
                foreach ($matches as $match) {
                    $timerMinutes = $match->match_timer_minutes ?? self::DEFAULT_MATCH_TIMER_MINUTES;
                    $dueAt = $match->started_at?->copy()->addMinutes($timerMinutes + self::IN_PROGRESS_GRACE_MINUTES);

                    if (! $dueAt || $dueAt->isFuture()) {
                        continue;
                    }

                    $match->dispute_notes = 'System timeout: no result was submitted before the H2H match timer and grace period expired.';
                    $match->save();

                    $matchStateMachine->transition($match, HeadToHeadMatchStatus::DISPUTED);
                }
            });
    }

    private function escalateStaleSubmittedResults(HeadToHeadMatchStateMachine $matchStateMachine): void
    {
        HeadToHeadMatch::query()
            ->where('status', HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION)
            ->whereNotNull('confirmation_due_at')
            ->where('confirmation_due_at', '<=', now())
            ->chunkById(100, function ($matches) use ($matchStateMachine): void {
                foreach ($matches as $match) {
                    $match->dispute_notes = 'System timeout: submitted H2H result was not confirmed or disputed before the response deadline.';
                    $match->save();

                    $matchStateMachine->transition($match, HeadToHeadMatchStatus::DISPUTED);
                }
            });
    }
}
