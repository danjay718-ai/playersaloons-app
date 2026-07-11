<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\Referral;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\UserStatus;
use Illuminate\Support\Facades\DB;

class AwardReferralRewardsAction
{
    public function __construct(private WalletService $walletService) {}

    public function execute(User $referredUser): void
    {
        DB::transaction(function () use ($referredUser): void {
            $referral = Referral::query()->where('referred_user_id', $referredUser->id)->lockForUpdate()->first();
            if (! $referral || $referral->status !== 'pending') {
                return;
            }

            $enabled = SystemSetting::query()->where('key', 'referral.enabled')->value('value') ?? 'true';
            if (! filter_var($enabled, FILTER_VALIDATE_BOOL)) {
                return;
            }

            $referrer = $referral->referrer()->with('wallet')->first();
            $referredUser->loadMissing('wallet');
            if (! $referrer || $referrer->status !== UserStatus::ACTIVE || ! $referrer->wallet || ! $referredUser->wallet) {
                return;
            }

            $referrerReward = number_format((float) (SystemSetting::query()->where('key', 'referral.referrer_reward')->value('value') ?? 5), 2, '.', '');
            $referredReward = number_format((float) (SystemSetting::query()->where('key', 'referral.referred_reward')->value('value') ?? 2), 2, '.', '');

            if ((float) $referrerReward > 0) {
                $this->walletService->credit($referrer->wallet, $referrerReward, LedgerType::REFERRAL_BONUS, 'referral_referrer', (string) $referral->id, 'Referral reward');
            }
            if ((float) $referredReward > 0) {
                $this->walletService->credit($referredUser->wallet, $referredReward, LedgerType::REFERRAL_BONUS, 'referral_referred', (string) $referral->id, 'New player referral reward');
            }

            $referral->update(['status' => 'rewarded', 'referrer_reward' => $referrerReward, 'referred_reward' => $referredReward, 'rewarded_at' => now()]);
        });
    }
}
