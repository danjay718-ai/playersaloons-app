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

    public function execute(HeadToHeadChallenge $challenge, User $opponent, string $opponentGameHandle, ?int $expectedGameId = null): HeadToHeadMatch
    {
        $opponentGameHandle = trim($opponentGameHandle);

        if ($opponentGameHandle === '') {
            throw new InvalidArgumentException('Opponent game handle is required.');
        }

        return DB::transaction(function () use ($challenge, $opponent, $opponentGameHandle, $expectedGameId): HeadToHeadMatch {
            /** @var HeadToHeadChallenge $lockedChallenge */
            $lockedChallenge = HeadToHeadChallenge::query()
                ->where('id', $challenge->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedChallenge->creator_user_id === $opponent->getKey()) {
                throw new LogicException('You cannot accept your own challenge.');
            }

            if ($expectedGameId !== null && $lockedChallenge->game_id !== $expectedGameId) {
                throw new LogicException('This challenge belongs to a different game. Switch to that game before accepting it.');
            }

            if ($lockedChallenge->status !== HeadToHeadChallengeStatus::WAITING) {
                throw new LogicException('Challenge is no longer available.');
            }

            if ($lockedChallenge->expires_at && $lockedChallenge->expires_at->isPast()) {
                $lockedChallenge->status = HeadToHeadChallengeStatus::EXPIRED;
                $lockedChallenge->save();

                throw new LogicException('Challenge has expired.');
            }

            $this->ensurePlayerCanAcceptGameDuel((int) $opponent->getKey(), (int) $lockedChallenge->game_id);

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

    private function ensurePlayerCanAcceptGameDuel(int $userId, int $gameId): void
    {
        $hasWaitingChallenge = HeadToHeadChallenge::query()
            ->where('creator_user_id', $userId)
            ->where('game_id', $gameId)
            ->where('status', HeadToHeadChallengeStatus::WAITING)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($hasWaitingChallenge) {
            throw new LogicException('You already have an open challenge for this game. Cancel it before accepting another duel.');
        }

        $hasActiveDuel = HeadToHeadMatch::query()
            ->where('game_id', $gameId)
            ->whereIn('status', [
                HeadToHeadMatchStatus::IN_PROGRESS,
                HeadToHeadMatchStatus::WAITING_FOR_CONFIRMATION,
                HeadToHeadMatchStatus::DISPUTED,
            ])
            ->where(function ($query) use ($userId) {
                $query->where('creator_user_id', $userId)
                    ->orWhere('opponent_user_id', $userId);
            })
            ->exists();

        if ($hasActiveDuel) {
            throw new LogicException('You already have an active duel for this game. Finish it before accepting another duel.');
        }
    }
}
