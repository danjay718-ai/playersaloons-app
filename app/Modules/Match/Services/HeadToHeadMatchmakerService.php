<?php

declare(strict_types=1);

namespace App\Modules\Match\Services;

use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Shared\Enums\HeadToHeadChallengeStatus;

class HeadToHeadMatchmakerService
{
    public function __construct(private readonly HeadToHeadRatingService $ratings) {}

    public function findOpponentChallenge(
        int $userId,
        int $gameId,
        float $stakeAmount,
        ?int $platformId = null,
        ?string $region = null
    ): ?HeadToHeadChallenge {
        $playerRating = $this->ratings->ratingFor($userId, $gameId);

        $candidates = HeadToHeadChallenge::query()
            ->select('head_to_head_challenges.*')
            ->selectRaw('COALESCE(head_to_head_ratings.rating, 1200) as skill_rating')
            ->leftJoin('head_to_head_ratings', function ($join): void {
                $join->on('head_to_head_ratings.user_id', '=', 'head_to_head_challenges.creator_user_id')
                    ->on('head_to_head_ratings.game_id', '=', 'head_to_head_challenges.game_id');
            })
            ->where('head_to_head_challenges.status', HeadToHeadChallengeStatus::WAITING->value)
            ->where('head_to_head_challenges.creator_user_id', '!=', $userId)
            ->where('head_to_head_challenges.game_id', $gameId)
            ->where('head_to_head_challenges.stake_amount', number_format($stakeAmount, 2, '.', ''))
            ->when($platformId !== null, fn ($query) => $query->where('head_to_head_challenges.platform_id', $platformId))
            ->when($region !== null && $region !== '', fn ($query) => $query->where('head_to_head_challenges.region', $region))
            ->where(function ($query) {
                $query->whereNull('head_to_head_challenges.expires_at')
                    ->orWhere('head_to_head_challenges.expires_at', '>', now());
            })
            ->orderByRaw('ABS(COALESCE(head_to_head_ratings.rating, 1200) - ?)', [$playerRating])
            ->orderBy('head_to_head_challenges.created_at')
            ->limit(100)
            ->get();

        return $candidates->first(function (HeadToHeadChallenge $challenge) use ($playerRating): bool {
            $waitMinutes = max(0, (int) abs($challenge->created_at->diffInMinutes(now())));
            $window = min(400, 100 + ($waitMinutes * 25));
            $challengeRating = (int) $challenge->getAttribute('skill_rating');

            return abs($challengeRating - $playerRating) <= $window;
        });
    }
}
