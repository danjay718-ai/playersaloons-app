<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use Illuminate\Support\Facades\DB;
use LogicException;

class CancelHeadToHeadChallengeAction
{
    public function __construct(private readonly RefundHeadToHeadStakeAction $refundStake) {}

    public function execute(HeadToHeadChallenge $challenge, User $actor): void
    {
        DB::transaction(function () use ($challenge, $actor): void {
            /** @var HeadToHeadChallenge $lockedChallenge */
            $lockedChallenge = HeadToHeadChallenge::query()
                ->where('id', $challenge->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedChallenge->creator_user_id !== $actor->getKey()) {
                throw new LogicException('Only the challenge creator can cancel this challenge.');
            }

            if ($lockedChallenge->status !== HeadToHeadChallengeStatus::WAITING) {
                throw new LogicException('Only waiting challenges can be cancelled.');
            }

            $lockedChallenge->status = HeadToHeadChallengeStatus::CANCELLED;
            $lockedChallenge->cancelled_at = now();
            $lockedChallenge->save();

            $this->refundStake->execute(
                $actor,
                (string) $lockedChallenge->stake_amount,
                HeadToHeadChallenge::class,
                (string) $lockedChallenge->getKey()
            );
        });
    }
}
