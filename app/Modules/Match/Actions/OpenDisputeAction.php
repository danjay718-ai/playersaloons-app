<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\DisputeStatus;
use App\Modules\Match\Events\MatchDisputed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OpenDisputeAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Open a dispute on the match.
     */
    public function execute(GameMatch $match, int $openedByUserId): MatchDispute
    {
        return DB::transaction(function () use ($match, $openedByUserId): MatchDispute {
            $playerAUserId = $match->playerARegistration?->user_id;
            $playerBUserId = $match->playerBRegistration?->user_id;

            if ($openedByUserId !== $playerAUserId && $openedByUserId !== $playerBUserId) {
                throw new InvalidArgumentException('Only match participants can open a dispute.');
            }

            $this->stateMachine->transition($match, MatchStatus::DISPUTED);

            $dispute = MatchDispute::query()->create([
                'uuid' => Str::uuid()->toString(),
                'match_id' => $match->id,
                'opened_by' => $openedByUserId,
                'status' => DisputeStatus::OPEN,
            ]);

            MatchDisputed::dispatch($match->id, $dispute->id, $openedByUserId);

            return $dispute;
        });
    }
}
