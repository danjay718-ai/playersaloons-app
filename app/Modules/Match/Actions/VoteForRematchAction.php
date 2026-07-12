<?php

declare(strict_types=1);

namespace App\Modules\Match\Actions;

use App\Modules\Match\Events\MatchRematchCreated;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchRematchVote;
use App\Modules\Operations\Models\SystemSetting;
use App\Shared\Enums\MatchStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class VoteForRematchAction
{
    public function execute(GameMatch $match, int $userId): ?GameMatch
    {
        return DB::transaction(function () use ($match, $userId): ?GameMatch {
            $locked = GameMatch::query()->lockForUpdate()->with(['playerARegistration', 'playerBRegistration'])->findOrFail($match->id);
            if (! in_array($locked->status, [MatchStatus::IN_PROGRESS, MatchStatus::WAITING_FOR_CONFIRMATION, MatchStatus::RESULT_SUBMITTED], true)) {
                throw new LogicException('Rematch voting is not available for this match.');
            }
            if (! $locked->playerARegistration?->includesUser($userId) && ! $locked->playerBRegistration?->includesUser($userId)) {
                throw new LogicException('Only match participants may vote for a rematch.');
            }
            if ($locked->disputes()->where('status', '!=', 'resolved')->exists()) {
                throw new LogicException('A rematch cannot be requested after a dispute is opened.');
            }

            $minutes = max(1, (int) (SystemSetting::query()->where('key', 'match.rematch_window_minutes')->value('value') ?? 30));
            MatchRematchVote::query()->updateOrCreate(
                ['match_id' => $locked->id, 'user_id' => $userId],
                ['expires_at' => now()->addMinutes($minutes)]
            );
            $voterIds = MatchRematchVote::query()->where('match_id', $locked->id)->where('expires_at', '>', now())->pluck('user_id');
            $sideAIds = $locked->playerARegistration?->rosterMembers()->pluck('user_id')->push($locked->playerARegistration->user_id)->unique() ?? collect();
            $sideBIds = $locked->playerBRegistration?->rosterMembers()->pluck('user_id')->push($locked->playerBRegistration->user_id)->unique() ?? collect();
            if (! $voterIds->intersect($sideAIds)->isNotEmpty() || ! $voterIds->intersect($sideBIds)->isNotEmpty()) {
                return null;
            }

            $locked->status = MatchStatus::COMPLETED;
            $locked->completed_at = now();
            $locked->save();
            $rematch = GameMatch::query()->create([
                'uuid' => Str::uuid()->toString(),
                'tournament_id' => $locked->tournament_id,
                'round_id' => $locked->round_id,
                'player_a_registration_id' => $locked->player_a_registration_id,
                'player_b_registration_id' => $locked->player_b_registration_id,
                'status' => MatchStatus::READY,
                'scheduled_at' => now(),
            ]);
            MatchRematchCreated::dispatch($locked->id, $rematch->id);

            return $rematch;
        });
    }
}
