<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PlayerDisputeStrike;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\StateMachines\UserStateMachine;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Models\TournamentRegistration;
use App\Shared\Enums\MatchStatus;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;

final class ApplyPendingDisputeBanAction
{
    // System action: only a persisted, administrator-issued pending strike can authorize a ban.
    public function execute(int $strikeId): void
    {
        $strike = PlayerDisputeStrike::query()->where('ban_pending', true)->find($strikeId);
        if ($strike === null) {
            return;
        }

        DB::transaction(function () use ($strike): void {
            $tournament = Tournament::withTrashed()->lockForUpdate()->findOrFail($strike->tournament_id);
            $player = User::query()->lockForUpdate()->findOrFail($strike->user_id);
            $pending = PlayerDisputeStrike::query()->lockForUpdate()->findOrFail($strike->id);
            if (! $pending->ban_pending) {
                return;
            }

            $registrationIds = TournamentRegistration::query()->where('tournament_id', $tournament->id)
                ->where(fn ($query) => $query->where('user_id', $player->id)
                    ->orWhereHas('rosterMembers', fn ($members) => $members->where('user_id', $player->id)))
                ->select('id');
            $eliminated = GameMatch::query()->where('tournament_id', $tournament->id)
                ->whereIn('status', [MatchStatus::COMPLETED, MatchStatus::FORFEITED])
                ->whereNotNull('winner_registration_id')
                ->whereNotIn('winner_registration_id', clone $registrationIds)
                ->where(fn ($query) => $query->whereIn('player_a_registration_id', clone $registrationIds)
                    ->orWhereIn('player_b_registration_id', clone $registrationIds))->exists();
            if (! $eliminated && ! in_array($tournament->status, [TournamentStatus::COMPLETED, TournamentStatus::CANCELLED, TournamentStatus::REFUNDED], true)) {
                return;
            }

            if ($player->status !== UserStatus::BANNED) {
                $from = $player->status;
                app(UserStateMachine::class)->transition($player, UserStatus::BANNED);
                activity()->causedBy($pending->issued_by)->performedOn($player)->withProperties([
                    'from_status' => $from->value, 'to_status' => UserStatus::BANNED->value,
                    'reason' => $pending->reason, 'source' => 'dispute_strikes',
                    'strike_id' => $pending->id, 'strike_number' => $pending->strike_number,
                ])->log('user_banned');
            }
            // Consume all currently pending strikes so a later manual unban is respected.
            PlayerDisputeStrike::query()->where('user_id', $player->id)->where('ban_pending', true)
                ->update(['ban_pending' => false, 'permanent_ban_applied' => true]);
        }, 3);
    }
}
