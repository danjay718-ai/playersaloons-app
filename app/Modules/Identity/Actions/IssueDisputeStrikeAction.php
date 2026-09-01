<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\PlayerDisputeStrike;
use App\Modules\Identity\Models\User;
use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\Models\MatchDispute;
use App\Modules\Tournament\Models\Tournament;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\UserStatus;
use App\Shared\Support\DecimalMoney;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class IssueDisputeStrikeAction
{
    public function __construct(private readonly WalletService $wallets) {}

    public function execute(MatchDispute $dispute, User $player, User $admin, string $reason, string $penalty = '0.00'): PlayerDisputeStrike
    {
        if (! $admin->hasAnyRole(['ADMIN', 'SUPER_ADMIN'])) {
            throw new AuthorizationException('Only administrators may issue dispute strikes.');
        }

        return DB::transaction(function () use ($dispute, $player, $admin, $reason, $penalty): PlayerDisputeStrike {
            $tournamentId = GameMatch::query()->whereKey($dispute->match_id)->value('tournament_id');
            Tournament::query()->lockForUpdate()->findOrFail($tournamentId);
            $match = $dispute->match()->with(['tournament', 'playerARegistration', 'playerBRegistration'])->lockForUpdate()->firstOrFail();
            if (! $match->playerARegistration?->includesUser($player->id)
                && ! $match->playerBRegistration?->includesUser($player->id)) {
                throw new LogicException('The strike target must be a participant in this match.');
            }

            $lockedPlayer = User::query()->lockForUpdate()->findOrFail($player->id);
            $number = PlayerDisputeStrike::query()->where('user_id', $player->id)->lockForUpdate()->count() + 1;
            $minor = DecimalMoney::toMinor($penalty);
            if ($minor < 0) {
                throw new LogicException('A balance penalty cannot be negative.');
            }

            $strike = PlayerDisputeStrike::query()->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $player->id,
                'tournament_id' => $match->tournament_id,
                'match_id' => $match->id,
                'dispute_id' => $dispute->id,
                'issued_by' => $admin->id,
                'strike_number' => $number,
                'reason' => $reason,
                'balance_penalty' => DecimalMoney::format($minor),
                'permanent_ban_applied' => $number >= 3,
                'created_at' => now(),
            ]);

            if ($minor > 0) {
                $wallet = $lockedPlayer->wallet()->lockForUpdate()->firstOrFail();
                $this->wallets->debit(
                    $wallet,
                    DecimalMoney::format($minor),
                    LedgerType::ADJUSTMENT,
                    'player_dispute_strike',
                    (string) $strike->uuid,
                    "Dispute strike #{$number}: {$reason}",
                    "dispute-strike-penalty:{$strike->id}",
                );
            }

            if ($number >= 3) {
                $lockedPlayer->update(['status' => UserStatus::BANNED]);
            }

            return $strike;
        }, 3);
    }
}
