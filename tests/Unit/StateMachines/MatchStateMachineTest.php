<?php

declare(strict_types=1);

namespace Tests\Unit\StateMachines;

use App\Modules\Match\Models\GameMatch;
use App\Modules\Match\StateMachines\MatchStateMachine;
use App\Shared\Enums\MatchStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatchStateMachineTest extends TestCase
{
    private MatchStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new MatchStateMachine;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockMatch(MatchStatus $status): GameMatch&MockInterface
    {
        /** @var GameMatch&MockInterface $m */
        $m = Mockery::mock(GameMatch::class)->makePartial();
        $m->status = $status;
        $m->shouldReceive('save')->andReturn(true);

        return $m;
    }

    // -------------------------------------------------------------------------
    // Valid transitions
    // -------------------------------------------------------------------------

    #[Test]
    public function pending_can_transition_to_ready(): void
    {
        $m = $this->mockMatch(MatchStatus::PENDING);
        $this->machine->transition($m, MatchStatus::READY);
        $this->assertEquals(MatchStatus::READY, $m->status);
    }

    #[Test]
    public function ready_can_transition_to_in_progress(): void
    {
        $m = $this->mockMatch(MatchStatus::READY);
        $this->machine->transition($m, MatchStatus::IN_PROGRESS);
        $this->assertEquals(MatchStatus::IN_PROGRESS, $m->status);
    }

    #[Test]
    public function in_progress_stamps_started_at(): void
    {
        $m = $this->mockMatch(MatchStatus::READY);
        $this->machine->transition($m, MatchStatus::IN_PROGRESS);
        $this->assertNotNull($m->started_at);
    }

    #[Test]
    public function in_progress_can_transition_to_waiting_for_confirmation(): void
    {
        $m = $this->mockMatch(MatchStatus::IN_PROGRESS);
        $this->machine->transition($m, MatchStatus::WAITING_FOR_CONFIRMATION);
        $this->assertEquals(MatchStatus::WAITING_FOR_CONFIRMATION, $m->status);
    }

    #[Test]
    public function waiting_for_confirmation_can_transition_to_completed(): void
    {
        $m = $this->mockMatch(MatchStatus::WAITING_FOR_CONFIRMATION);
        $this->machine->transition($m, MatchStatus::COMPLETED);
        $this->assertEquals(MatchStatus::COMPLETED, $m->status);
    }

    #[Test]
    public function completed_stamps_completed_at(): void
    {
        $m = $this->mockMatch(MatchStatus::WAITING_FOR_CONFIRMATION);
        $this->machine->transition($m, MatchStatus::COMPLETED);
        $this->assertNotNull($m->completed_at);
    }

    // -------------------------------------------------------------------------
    // Dispute path
    // -------------------------------------------------------------------------

    #[Test]
    public function in_progress_can_transition_to_disputed(): void
    {
        $m = $this->mockMatch(MatchStatus::IN_PROGRESS);
        $this->machine->transition($m, MatchStatus::DISPUTED);
        $this->assertEquals(MatchStatus::DISPUTED, $m->status);
    }

    #[Test]
    public function waiting_for_confirmation_can_transition_to_disputed(): void
    {
        $m = $this->mockMatch(MatchStatus::WAITING_FOR_CONFIRMATION);
        $this->machine->transition($m, MatchStatus::DISPUTED);
        $this->assertEquals(MatchStatus::DISPUTED, $m->status);
    }

    #[Test]
    public function legacy_result_submitted_can_transition_to_completed(): void
    {
        $m = $this->mockMatch(MatchStatus::RESULT_SUBMITTED);
        $this->machine->transition($m, MatchStatus::COMPLETED);
        $this->assertEquals(MatchStatus::COMPLETED, $m->status);
    }

    #[Test]
    public function disputed_can_be_resolved_to_completed(): void
    {
        $m = $this->mockMatch(MatchStatus::DISPUTED);
        $this->machine->transition($m, MatchStatus::COMPLETED);
        $this->assertEquals(MatchStatus::COMPLETED, $m->status);
    }

    // -------------------------------------------------------------------------
    // Forfeit path
    // -------------------------------------------------------------------------

    #[Test]
    public function ready_can_transition_to_forfeited(): void
    {
        $m = $this->mockMatch(MatchStatus::READY);
        $this->machine->transition($m, MatchStatus::FORFEITED);
        $this->assertEquals(MatchStatus::FORFEITED, $m->status);
    }

    #[Test]
    public function in_progress_can_transition_to_forfeited(): void
    {
        $m = $this->mockMatch(MatchStatus::IN_PROGRESS);
        $this->machine->transition($m, MatchStatus::FORFEITED);
        $this->assertEquals(MatchStatus::FORFEITED, $m->status);
    }

    // -------------------------------------------------------------------------
    // Invalid transitions
    // -------------------------------------------------------------------------

    #[Test]
    public function pending_cannot_jump_to_completed(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $m = $this->mockMatch(MatchStatus::PENDING);
        $this->machine->transition($m, MatchStatus::COMPLETED);
    }

    #[Test]
    public function completed_is_terminal(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $m = $this->mockMatch(MatchStatus::COMPLETED);
        $this->machine->transition($m, MatchStatus::IN_PROGRESS);
    }

    #[Test]
    public function forfeited_is_terminal(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $m = $this->mockMatch(MatchStatus::FORFEITED);
        $this->machine->transition($m, MatchStatus::COMPLETED);
    }

    #[Test]
    public function in_progress_cannot_loop_to_itself(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $m = $this->mockMatch(MatchStatus::IN_PROGRESS);
        $this->machine->transition($m, MatchStatus::IN_PROGRESS);
    }
}
