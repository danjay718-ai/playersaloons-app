<?php

declare(strict_types=1);

namespace App\Modules\Tournament\Actions;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\Services\V2PrizePolicy;
use App\Shared\Enums\RegistrationStatus;
use App\Shared\Support\DecimalMoney;
use Illuminate\Support\Facades\DB;

final class FinalizeV2FinancialsAction
{
    public function __construct(private readonly V2PrizePolicy $policy) {}

    /** @return array<string, int|string|bool> */
    public function execute(Tournament $tournament): array
    {
        return DB::transaction(function () use ($tournament): array {
            $locked = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            $joinedEntries = $locked->registrations()
                ->where('status', RegistrationStatus::CONFIRMED)
                ->count();
            $result = $this->policy->calculate($locked, $joinedEntries);

            if ($locked->financial_finalized_at === null) {
                $locked->forceFill([
                    'finalized_joined_entries' => $joinedEntries,
                    'finalized_gross_pool' => $result['gross'],
                    'finalized_commission_amount' => $result['commission'],
                    'finalized_first_prize' => $result['first'],
                    'finalized_second_prize' => $result['second'],
                    'prize_pool' => DecimalMoney::format((int) $result['first_minor'] + (int) $result['second_minor']),
                    'financial_finalized_at' => now(),
                ])->save();
            }

            return $result;
        });
    }
}
