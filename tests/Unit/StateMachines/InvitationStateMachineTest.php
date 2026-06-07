<?php

declare(strict_types=1);

namespace Tests\Unit\StateMachines;

use App\Modules\Team\Models\TeamInvitation;
use App\Modules\Team\StateMachines\InvitationStateMachine;
use App\Shared\Enums\TeamInvitationStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Carbon\Carbon;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class InvitationStateMachineTest extends TestCase
{
    private InvitationStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new InvitationStateMachine;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeInvitation(TeamInvitationStatus $status, ?Carbon $expiresAt = null): TeamInvitation
    {
        /** @var TeamInvitation&MockInterface $invitation */
        $invitation = Mockery::mock(TeamInvitation::class)->makePartial();
        $invitation->status = $status;
        $invitation->expires_at = $expiresAt;
        $invitation->shouldReceive('save')->once();

        return $invitation;
    }

    private function makeInvitationNoSave(TeamInvitationStatus $status, ?Carbon $expiresAt = null): TeamInvitation
    {
        /** @var TeamInvitation&MockInterface $invitation */
        $invitation = Mockery::mock(TeamInvitation::class)->makePartial();
        $invitation->status = $status;
        $invitation->expires_at = $expiresAt;
        $invitation->shouldNotReceive('save');

        return $invitation;
    }

    // ------------------------------------------------------------------ //
    // Valid transitions
    // ------------------------------------------------------------------ //

    public function test_pending_to_accepted_when_not_expired_succeeds(): void
    {
        $expiresAt = Carbon::now()->addDays(7);
        $invitation = $this->makeInvitation(TeamInvitationStatus::PENDING, $expiresAt);

        $this->machine->transition($invitation, TeamInvitationStatus::ACCEPTED);

        $this->assertSame(TeamInvitationStatus::ACCEPTED, $invitation->status);
    }

    public function test_pending_to_declined_valid(): void
    {
        $expiresAt = Carbon::now()->addDays(1);
        $invitation = $this->makeInvitation(TeamInvitationStatus::PENDING, $expiresAt);

        $this->machine->transition($invitation, TeamInvitationStatus::DECLINED);

        $this->assertSame(TeamInvitationStatus::DECLINED, $invitation->status);
    }

    public function test_pending_to_expired_valid(): void
    {
        $invitation = $this->makeInvitation(TeamInvitationStatus::PENDING);

        $this->machine->transition($invitation, TeamInvitationStatus::EXPIRED);

        $this->assertSame(TeamInvitationStatus::EXPIRED, $invitation->status);
    }

    public function test_pending_to_revoked_valid(): void
    {
        $invitation = $this->makeInvitation(TeamInvitationStatus::PENDING);

        $this->machine->transition($invitation, TeamInvitationStatus::REVOKED);

        $this->assertSame(TeamInvitationStatus::REVOKED, $invitation->status);
    }

    // ------------------------------------------------------------------ //
    // Expiry guard
    // ------------------------------------------------------------------ //

    public function test_pending_to_accepted_when_expired_throws_logic_exception(): void
    {
        $expiresAt = Carbon::now()->subMinutes(1);
        $invitation = $this->makeInvitationNoSave(TeamInvitationStatus::PENDING, $expiresAt);

        $this->expectException(\LogicException::class);

        $this->machine->transition($invitation, TeamInvitationStatus::ACCEPTED);
    }

    public function test_pending_to_declined_when_expired_throws_logic_exception(): void
    {
        $expiresAt = Carbon::now()->subMinutes(1);
        $invitation = $this->makeInvitationNoSave(TeamInvitationStatus::PENDING, $expiresAt);

        $this->expectException(\LogicException::class);

        $this->machine->transition($invitation, TeamInvitationStatus::DECLINED);
    }

    // ------------------------------------------------------------------ //
    // Invalid transitions from terminal states
    // ------------------------------------------------------------------ //

    public function test_accepted_to_pending_throws_invalid_transition(): void
    {
        $invitation = $this->makeInvitationNoSave(TeamInvitationStatus::ACCEPTED);

        $this->expectException(InvalidStateTransitionException::class);

        $this->machine->transition($invitation, TeamInvitationStatus::PENDING);
    }

    public function test_accepted_to_declined_throws_invalid_transition(): void
    {
        $invitation = $this->makeInvitationNoSave(TeamInvitationStatus::ACCEPTED);

        $this->expectException(InvalidStateTransitionException::class);

        $this->machine->transition($invitation, TeamInvitationStatus::DECLINED);
    }
}
