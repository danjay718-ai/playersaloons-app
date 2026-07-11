<?php

declare(strict_types=1);

namespace App\Modules\Match\Services;

use App\Modules\Match\Models\HeadToHeadMatch;
use App\Modules\Match\Models\HeadToHeadRating;
use Illuminate\Support\Facades\DB;
use LogicException;

class HeadToHeadRatingService
{
    public const DEFAULT_RATING = 1200;

    public function ratingFor(int $userId, int $gameId): int
    {
        return (int) (HeadToHeadRating::query()
            ->where('user_id', $userId)
            ->where('game_id', $gameId)
            ->value('rating') ?? self::DEFAULT_RATING);
    }

    public function process(HeadToHeadMatch $match): void
    {
        DB::transaction(function () use ($match): void {
            $lockedMatch = HeadToHeadMatch::query()->lockForUpdate()->findOrFail($match->id);
            if ($lockedMatch->rating_processed_at !== null) {
                return;
            }
            if (! $lockedMatch->winner_user_id) {
                throw new LogicException('A winner is required before processing ratings.');
            }

            $creator = HeadToHeadRating::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $lockedMatch->creator_user_id, 'game_id' => $lockedMatch->game_id],
                ['rating' => self::DEFAULT_RATING, 'wins' => 0, 'losses' => 0],
            );
            $opponent = HeadToHeadRating::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $lockedMatch->opponent_user_id, 'game_id' => $lockedMatch->game_id],
                ['rating' => self::DEFAULT_RATING, 'wins' => 0, 'losses' => 0],
            );

            $creatorBefore = $creator->rating;
            $opponentBefore = $opponent->rating;
            $creatorScore = $lockedMatch->winner_user_id === $lockedMatch->creator_user_id ? 1.0 : 0.0;
            $creatorExpected = 1 / (1 + (10 ** (($opponentBefore - $creatorBefore) / 400)));
            $creatorAfter = max(100, (int) round($creatorBefore + 32 * ($creatorScore - $creatorExpected)));
            $opponentAfter = max(100, (int) round($opponentBefore + 32 * ((1 - $creatorScore) - (1 - $creatorExpected))));

            $creator->update([
                'rating' => $creatorAfter,
                'wins' => $creator->wins + (int) $creatorScore,
                'losses' => $creator->losses + (int) (1 - $creatorScore),
            ]);
            $opponent->update([
                'rating' => $opponentAfter,
                'wins' => $opponent->wins + (int) (1 - $creatorScore),
                'losses' => $opponent->losses + (int) $creatorScore,
            ]);
            $lockedMatch->update([
                'creator_rating_before' => $creatorBefore,
                'creator_rating_after' => $creatorAfter,
                'opponent_rating_before' => $opponentBefore,
                'opponent_rating_after' => $opponentAfter,
                'rating_processed_at' => now(),
            ]);
        });
    }
}
