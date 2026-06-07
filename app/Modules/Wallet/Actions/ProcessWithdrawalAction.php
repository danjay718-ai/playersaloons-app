<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\StateMachines\WithdrawalStateMachine;
use App\Shared\Enums\WithdrawalStatus;
use Illuminate\Support\Facades\DB;

class ProcessWithdrawalAction
{
    public function __construct(private readonly WithdrawalStateMachine $stateMachine) {}

    /**
     * Mark a withdrawal as processed.
     *
     * @param Withdrawal $withdrawal
     * @return void
     */
    public function execute(Withdrawal $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal): void {
            $this->stateMachine->transition($withdrawal, WithdrawalStatus::PROCESSED);

            $withdrawal->update([
                'processed_at' => now(),
            ]);
        });
    }
}
