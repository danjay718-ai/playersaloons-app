<?php

declare(strict_types=1);

namespace App\Modules\Match\Services;

use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Shared\Enums\HeadToHeadChallengeStatus;

class HeadToHeadMatchmakerService
{
    public function findOpponentChallenge(
        int $userId,
        int $gameId,
        float $stakeAmount,
        ?int $platformId = null,
        ?string $region = null
    ): ?HeadToHeadChallenge {
        return HeadToHeadChallenge::query()
            ->where('status', HeadToHeadChallengeStatus::WAITING->value)
            ->where('creator_user_id', '!=', $userId)
            ->where('game_id', $gameId)
            ->where('stake_amount', number_format($stakeAmount, 2, '.', ''))
            ->when($platformId !== null, fn ($query) => $query->where('platform_id', $platformId))
            ->when($region !== null && $region !== '', fn ($query) => $query->where('region', $region))
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->oldest()
            ->first();
    }
}
