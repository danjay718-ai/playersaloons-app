<?php

declare(strict_types=1);

namespace Tests\Unit\StateMachines;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\StateMachines\WithdrawalStateMachine;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\WithdrawalStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class WithdrawalStateMachineTest extends TestCase
{
    private WithdrawalStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new WithdrawalStateMachine;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeWithdrawal(WithdrawalStatus $status, float $amount = 100.0): Withdrawal
    {
        /** @var Withdrawal&MockInterface $withdrawal */
        $withdrawal = Mockery::mock(Withdrawal::class)->makePartial();
        $withdrawal->status = $status;
        $withdrawal->amount = $amount;

        return $withdrawal;
    }

    private function makeApprovedKyc(): KycSubmission
    {
        /** @var KycSubmission&MockInterface $kyc */
        $kyc = Mockery::mock(KycSubmission::class)->makePartial();
        $kyc->status = KycStatus::APPROVED;

        return $kyc;
    }

    private function makePendingKyc(): KycSubmission
    {
        /** @var KycSubmission&MockInterface $kyc */
        $kyc = Mockery::mock(KycSubmission::class)->makePartial();
        $kyc->status = KycStatus::UNDER_REVIEW;

        return $kyc;
    }

    private function makeWalletWithBalance(float $balance): Wallet
    {
        /** @var Wallet&MockInterface $wallet */
        $wallet = Mockery::mock(Wallet::class)->makePartial();
        $wallet->cached_balance = $balance;

        return $wallet;
    }

    // ------------------------------------------------------------------ //
    // PENDING → UNDER_REVIEW (valid first step toward approval)
    // ------------------------------------------------------------------ //

    public function test_pending_to_under_review_valid(): void
    {
        $withdrawal = $this->makeWithdrawal(WithdrawalStatus::PENDING);
        $withdrawal->shouldReceive('save')->once();

        $this->machine->transition($withdrawal, WithdrawalStatus::UNDER_REVIEW);

        $this->assertSame(WithdrawalStatus::UNDER_REVIEW, $withdrawal->status);
    }

    // ------------------------------------------------------------------ //
    // UNDER_REVIEW → APPROVED guards
    // ------------------------------------------------------------------ //

    public function test_under_review_to_approved_with_approved_kyc_and_sufficient_balance_succeeds(): void
    {
        $withdrawal = $this->makeWithdrawal(WithdrawalStatus::UNDER_REVIEW, 50.0);
        $withdrawal->shouldReceive('save')->once();

        $kyc = $this->makeApprovedKyc();
        $wallet = $this->makeWalletWithBalance(200.0);

        $this->machine->transition($withdrawal, WithdrawalStatus::APPROVED, $kyc, $wallet);

        $this->assertSame(WithdrawalStatus::APPROVED, $withdrawal->status);
    }

    public function test_under_review_to_approved_with_non_approved_kyc_throws_logic_exception(): void
    {
        $withdrawal = $this->makeWithdrawal(WithdrawalStatus::UNDER_REVIEW, 50.0);
        $withdrawal->shouldNotReceive('save');

        $kyc = $this->makePendingKyc();
        $wallet = $this->makeWalletWithBalance(200.0);

        $this->expectException(\LogicException::class);

        $this->machine->transition($withdrawal, WithdrawalStatus::APPROVED, $kyc, $wallet);
    }

    public function test_under_review_to_approved_with_insufficient_balance_throws_logic_exception(): void
    {
        $withdrawal = $this->makeWithdrawal(WithdrawalStatus::UNDER_REVIEW, 500.0);
        $withdrawal->shouldNotReceive('save');

        $kyc = $this->makeApprovedKyc();
        $wallet = $this->makeWalletWithBalance(100.0);

        $this->expectException(\LogicException::class);

        $this->machine->transition($withdrawal, WithdrawalStatus::APPROVED, $kyc, $wallet);
    }

    // ------------------------------------------------------------------ //
    // Retry path: REJECTED → PENDING
    // ------------------------------------------------------------------ //

    public function test_rejected_to_pending_retry_valid(): void
    {
        $withdrawal = $this->makeWithdrawal(WithdrawalStatus::REJECTED);
        $withdrawal->shouldReceive('save')->once();

        $this->machine->transition($withdrawal, WithdrawalStatus::PENDING);

        $this->assertSame(WithdrawalStatus::PENDING, $withdrawal->status);
    }

    // ------------------------------------------------------------------ //
    // Terminal state: PROCESSED → anything throws
    // ------------------------------------------------------------------ //

    public function test_processed_to_any_throws_invalid_transition(): void
    {
        $withdrawal = $this->makeWithdrawal(WithdrawalStatus::PROCESSED);
        $withdrawal->shouldNotReceive('save');

        $this->expectException(InvalidStateTransitionException::class);

        $this->machine->transition($withdrawal, WithdrawalStatus::PENDING);
    }

    public function test_processed_to_approved_throws_invalid_transition(): void
    {
        $withdrawal = $this->makeWithdrawal(WithdrawalStatus::PROCESSED);
        $withdrawal->shouldNotReceive('save');

        $this->expectException(InvalidStateTransitionException::class);

        $this->machine->transition($withdrawal, WithdrawalStatus::APPROVED);
    }
}
