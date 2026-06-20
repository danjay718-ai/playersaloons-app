<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Modules\Match\Models\HeadToHeadMatch;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use App\Shared\Enums\HeadToHeadMatchStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

class AcceptHeadToHeadChallengeAction
{
    public function __construct(private readonly LockHeadToHeadStakeAction $lockStake) {}

    public function execute(HeadToHeadChallenge $challenge, User $opponent, string $opponentGameHandle): HeadToHeadMatch
    {
        $opponentGameHandle = trim($opponentGameHandle);

        if ($opponentGameHandle === '') {
            throw new InvalidArgumentException('Opponent game handle is required.');
        }

        return DB::transaction(function () use ($challenge, $opponent, $opponentGameHandle): HeadToHeadMatch {
            /** @var HeadToHeadChallenge $lockedChallenge */
            $lockedChallenge = HeadToHeadChallenge::query()
                ->where('id', $challenge->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedChallenge->creator_user_id === $opponent->getKey()) {
                throw new LogicException('You cannot accept your own challenge.');
            }

            if ($lockedChallenge->status !== HeadToHeadChallengeStatus::WAITING) {
                throw new LogicException('Challenge is no longer available.');
            }

            if ($lockedChallenge->expires_at && $lockedChallenge->expires_at->isPast()) {
                $lockedChallenge->status = HeadToHeadChallengeStatus::EXPIRED;
                $lockedChallenge->save();

                throw new LogicException('Challenge has expired.');
            }

            $match = HeadToHeadMatch::query()->create([
                'uuid' => Str::uuid()->toString(),
                'challenge_id' => $lockedChallenge->getKey(),
                'creator_user_id' => $lockedChallenge->creator_user_id,
                'opponent_user_id' => $opponent->getKey(),
                'game_id' => $lockedChallenge->game_id,
                'platform_id' => $lockedChallenge->platform_id,
                'stake_amount' => $lockedChallenge->stake_amount,
                'status' => HeadToHeadMatchStatus::IN_PROGRESS,
                'creator_game_handle' => $lockedChallenge->creator_game_handle,
                'opponent_game_handle' => $opponentGameHandle,
                'region' => $lockedChallenge->region,
                'match_timer_minutes' => $lockedChallenge->match_timer_minutes,
                'started_at' => now(),
            ]);

            $this->lockStake->execute(
                $opponent,
                (string) $lockedChallenge->stake_amount,
                HeadToHeadMatch::class,
                (string) $match->getKey()
            );

            $lockedChallenge->status = HeadToHeadChallengeStatus::MATCHED;
            $lockedChallenge->matched_at = now();
            $lockedChallenge->save();

            return $match;
        });
    }
}
