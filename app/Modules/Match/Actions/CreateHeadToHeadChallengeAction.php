<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\CMS\Models\Game;
use App\Modules\CMS\Models\Platform;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\HeadToHeadChallenge;
use App\Shared\Enums\HeadToHeadChallengeStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateHeadToHeadChallengeAction
{
    public function __construct(private readonly LockHeadToHeadStakeAction $lockStake) {}

    /**
     * @param array{game_id:int, platform_id?:int|null, stake_amount:float|int|string, creator_game_handle:string, region?:string|null, match_timer_minutes?:int|null} $data
     */
    public function execute(User $creator, array $data): HeadToHeadChallenge
    {
        $stakeAmount = (float) $data['stake_amount'];

        if ($stakeAmount <= 0) {
            throw new InvalidArgumentException('Stake amount must be greater than zero.');
        }

        $game = Game::query()->where('is_active', true)->findOrFail($data['game_id']);
        $platformId = $data['platform_id'] ?? null;

        if ($platformId !== null) {
            Platform::query()->where('is_active', true)->findOrFail($platformId);
        }

        $gameHandle = trim($data['creator_game_handle']);
        if ($gameHandle === '') {
            throw new InvalidArgumentException('Game handle is required.');
        }

        return DB::transaction(function () use ($creator, $data, $stakeAmount, $game, $platformId, $gameHandle): HeadToHeadChallenge {
            $challenge = HeadToHeadChallenge::query()->create([
                'uuid' => Str::uuid()->toString(),
                'creator_user_id' => $creator->getKey(),
                'game_id' => $game->getKey(),
                'platform_id' => $platformId,
                'stake_amount' => $stakeAmount,
                'status' => HeadToHeadChallengeStatus::WAITING,
                'creator_game_handle' => $gameHandle,
                'region' => $data['region'] ?? null,
                'match_timer_minutes' => $data['match_timer_minutes'] ?? null,
                'expires_at' => now()->addMinutes(15),
            ]);

            $this->lockStake->execute(
                $creator,
                $stakeAmount,
                HeadToHeadChallenge::class,
                (string) $challenge->getKey()
            );

            return $challenge;
        });
    }
}
