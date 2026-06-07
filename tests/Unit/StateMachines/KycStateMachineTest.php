<?php

declare(strict_types=1);

namespace Tests\Unit\StateMachines;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\StateMachines\KycStateMachine;
use App\Shared\Enums\KycStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class KycStateMachineTest extends TestCase
{
    private KycStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new KycStateMachine;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeKyc(KycStatus $status): KycSubmission
    {
        /** @var KycSubmission&MockInterface $kyc */
        $kyc = Mockery::mock(KycSubmission::class)->makePartial();
        $kyc->status = $status;
        $kyc->shouldReceive('save')->once();

        return $kyc;
    }

    private function makeKycNoSave(KycStatus $status): KycSubmission
    {
        /** @var KycSubmission&MockInterface $kyc */
        $kyc = Mockery::mock(KycSubmission::class)->makePartial();
        $kyc->status = $status;
        $kyc->shouldNotReceive('save');

        return $kyc;
    }

    // ------------------------------------------------------------------ //
    // Valid transitions
    // ------------------------------------------------------------------ //

    public function test_not_submitted_to_submitted(): void
    {
        $kyc = $this->makeKyc(KycStatus::NOT_SUBMITTED);
        $this->machine->transition($kyc, KycStatus::SUBMITTED);

        $this->assertSame(KycStatus::SUBMITTED, $kyc->status);
    }

    public function test_submitted_to_under_review(): void
    {
        $kyc = $this->makeKyc(KycStatus::SUBMITTED);
        $this->machine->transition($kyc, KycStatus::UNDER_REVIEW);

        $this->assertSame(KycStatus::UNDER_REVIEW, $kyc->status);
    }

    public function test_under_review_to_approved(): void
    {
        $kyc = $this->makeKyc(KycStatus::UNDER_REVIEW);
        $this->machine->transition($kyc, KycStatus::APPROVED);

        $this->assertSame(KycStatus::APPROVED, $kyc->status);
    }

    public function test_under_review_to_rejected(): void
    {
        $kyc = $this->makeKyc(KycStatus::UNDER_REVIEW);
        $this->machine->transition($kyc, KycStatus::REJECTED);

        $this->assertSame(KycStatus::REJECTED, $kyc->status);
    }

    public function test_rejected_to_submitted_resubmit(): void
    {
        $kyc = $this->makeKyc(KycStatus::REJECTED);
        $this->machine->transition($kyc, KycStatus::SUBMITTED);

        $this->assertSame(KycStatus::SUBMITTED, $kyc->status);
    }

    // ------------------------------------------------------------------ //
    // Invalid transitions
    // ------------------------------------------------------------------ //

    public function test_approved_to_submitted_throws_invalid_transition(): void
    {
        $kyc = $this->makeKycNoSave(KycStatus::APPROVED);

        $this->expectException(InvalidStateTransitionException::class);

        $this->machine->transition($kyc, KycStatus::SUBMITTED);
    }

    public function test_approved_to_under_review_throws_invalid_transition(): void
    {
        $kyc = $this->makeKycNoSave(KycStatus::APPROVED);

        $this->expectException(InvalidStateTransitionException::class);

        $this->machine->transition($kyc, KycStatus::UNDER_REVIEW);
    }

    public function test_approved_to_rejected_throws_invalid_transition(): void
    {
        $kyc = $this->makeKycNoSave(KycStatus::APPROVED);

        $this->expectException(InvalidStateTransitionException::class);

        $this->machine->transition($kyc, KycStatus::REJECTED);
    }
}
