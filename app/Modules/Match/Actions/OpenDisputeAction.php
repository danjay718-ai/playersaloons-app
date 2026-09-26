<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchDisputed;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\DisputeStatus;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OpenDisputeAction
{
    public function __construct(private readonly MatchStateMachine $stateMachine) {}

    /**
     * Open a dispute on the match.
     */
    public function execute(GameMatch $match, int $openedByUserId, string $reason = ''): MatchDispute
    {
        return DB::transaction(function () use ($match, $openedByUserId, $reason): MatchDispute {
            $tournamentId = GameMatch::query()->whereKey($match->id)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $locked = GameMatch::query()
                ->with(['playerARegistration', 'playerBRegistration'])
                ->lockForUpdate()
                ->findOrFail($match->id);

            if (! $locked->playerARegistration?->includesUser($openedByUserId) && ! $locked->playerBRegistration?->includesUser($openedByUserId)) {
                throw new InvalidArgumentException('Only match participants can open a dispute.');
            }

            $this->stateMachine->transition($locked, MatchStatus::DISPUTED);

            $dispute = MatchDispute::query()->create([
                'uuid' => Str::uuid()->toString(),
                'match_id' => $locked->id,
                'opened_by' => $openedByUserId,
                'status' => DisputeStatus::OPEN,
                'reason' => trim($reason),
            ]);

            MatchDisputed::dispatch($locked->id, $dispute->id, $openedByUserId);

            return $dispute;
        });
    }
}
