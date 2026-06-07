<?php

declare(strict_types=1);

namespace Tests\Unit\StateMachines;

use App\Modules\Tournament\Models\Tournament;
use App\Modules\Tournament\StateMachines\TournamentStateMachine;
use App\Shared\Enums\TournamentStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use LogicException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TournamentStateMachineTest extends TestCase
{
    private TournamentStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new TournamentStateMachine;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function mockTournament(TournamentStatus $status): Tournament&MockInterface
    {
        /** @var Tournament&MockInterface $t */
        $t = Mockery::mock(Tournament::class)->makePartial();
        $t->status = $status;
        $t->shouldReceive('save')->andReturn(true);

        return $t;
    }

    // -------------------------------------------------------------------------
    // Valid transitions
    // -------------------------------------------------------------------------

    #[Test]
    public function draft_can_transition_to_published(): void
    {
        $t = $this->mockTournament(TournamentStatus::DRAFT);
        $t->name = 'Test Tournament';
        $t->max_participants = 8;
        $t->min_participants = 4;
        $t->registration_open_at = now()->addDay();
        $t->registration_close_at = now()->addDays(3);

        $this->machine->transition($t, TournamentStatus::PUBLISHED);

        $this->assertEquals(TournamentStatus::PUBLISHED, $t->status);
    }

    #[Test]
    public function draft_can_transition_to_cancelled(): void
    {
        $t = $this->mockTournament(TournamentStatus::DRAFT);

        $this->machine->transition($t, TournamentStatus::CANCELLED);

        $this->assertEquals(TournamentStatus::CANCELLED, $t->status);
    }

    #[Test]
    public function published_can_transition_to_registration_open(): void
    {
        $t = $this->mockTournament(TournamentStatus::PUBLISHED);

        $this->machine->transition($t, TournamentStatus::REGISTRATION_OPEN);

        $this->assertEquals(TournamentStatus::REGISTRATION_OPEN, $t->status);
    }

    #[Test]
    public function registration_open_can_transition_to_registration_closed(): void
    {
        $t = $this->mockTournament(TournamentStatus::REGISTRATION_OPEN);

        $this->machine->transition($t, TournamentStatus::REGISTRATION_CLOSED);

        $this->assertEquals(TournamentStatus::REGISTRATION_CLOSED, $t->status);
    }

    #[Test]
    public function checkin_closed_can_transition_to_bracket_generated(): void
    {
        $t = $this->mockTournament(TournamentStatus::CHECKIN_CLOSED);
        $t->min_participants = 2;

        // Mock participants relationship
        $participantQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
        $participantQuery->shouldReceive('count')->andReturn(4);
        $t->shouldReceive('participants')->andReturn($participantQuery);

        $this->machine->transition($t, TournamentStatus::BRACKET_GENERATED);

        $this->assertEquals(TournamentStatus::BRACKET_GENERATED, $t->status);
    }

    #[Test]
    public function bracket_generated_can_transition_to_ongoing(): void
    {
        $t = $this->mockTournament(TournamentStatus::BRACKET_GENERATED);

        // Mock brackets relationship
        $bracketQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
        $bracketQuery->shouldReceive('exists')->andReturn(true);
        $t->shouldReceive('brackets')->andReturn($bracketQuery);

        $this->machine->transition($t, TournamentStatus::ONGOING);

        $this->assertEquals(TournamentStatus::ONGOING, $t->status);
    }

    #[Test]
    public function ongoing_can_transition_to_completed(): void
    {
        $t = $this->mockTournament(TournamentStatus::ONGOING);

        $this->machine->transition($t, TournamentStatus::COMPLETED);

        $this->assertEquals(TournamentStatus::COMPLETED, $t->status);
    }

    #[Test]
    public function completed_can_transition_to_refunded(): void
    {
        $t = $this->mockTournament(TournamentStatus::COMPLETED);

        $this->machine->transition($t, TournamentStatus::REFUNDED);

        $this->assertEquals(TournamentStatus::REFUNDED, $t->status);
    }

    #[Test]
    public function cancelled_can_transition_to_refunded(): void
    {
        $t = $this->mockTournament(TournamentStatus::CANCELLED);

        $this->machine->transition($t, TournamentStatus::REFUNDED);

        $this->assertEquals(TournamentStatus::REFUNDED, $t->status);
    }

    // -------------------------------------------------------------------------
    // Invalid transitions
    // -------------------------------------------------------------------------

    #[Test]
    public function draft_cannot_transition_to_ongoing(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $t = $this->mockTournament(TournamentStatus::DRAFT);
        $this->machine->transition($t, TournamentStatus::ONGOING);
    }

    #[Test]
    public function ongoing_cannot_be_cancelled(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $t = $this->mockTournament(TournamentStatus::ONGOING);
        $this->machine->transition($t, TournamentStatus::CANCELLED);
    }

    #[Test]
    public function refunded_is_terminal(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $t = $this->mockTournament(TournamentStatus::REFUNDED);
        $this->machine->transition($t, TournamentStatus::DRAFT);
    }

    #[Test]
    public function completed_cannot_be_cancelled(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $t = $this->mockTournament(TournamentStatus::COMPLETED);
        $this->machine->transition($t, TournamentStatus::CANCELLED);
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    #[Test]
    public function guard_can_publish_throws_when_max_participants_too_low(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/max_participants/');

        $t = $this->mockTournament(TournamentStatus::DRAFT);
        $t->name = 'Test';
        $t->max_participants = 1; // too low
        $t->registration_open_at = now()->addDay();
        $t->registration_close_at = now()->addDays(3);

        $this->machine->guardCanPublish($t);
    }

    #[Test]
    public function guard_can_publish_throws_when_registration_dates_missing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/registration_open_at/');

        $t = $this->mockTournament(TournamentStatus::DRAFT);
        $t->name = 'Test';
        $t->max_participants = 8;
        $t->registration_open_at = null;
        $t->registration_close_at = null;

        $this->machine->guardCanPublish($t);
    }

    #[Test]
    public function guard_can_start_throws_when_no_bracket(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Bracket/');

        $t = $this->mockTournament(TournamentStatus::BRACKET_GENERATED);

        $bracketQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
        $bracketQuery->shouldReceive('exists')->andReturn(false);
        $t->shouldReceive('brackets')->andReturn($bracketQuery);

        $this->machine->guardCanStart($t);
    }

    #[Test]
    public function guard_can_generate_bracket_throws_when_not_enough_participants(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/participants/');

        $t = $this->mockTournament(TournamentStatus::CHECKIN_CLOSED);
        $t->min_participants = 8;

        $participantQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
        $participantQuery->shouldReceive('count')->andReturn(2); // below minimum
        $t->shouldReceive('participants')->andReturn($participantQuery);

        $this->machine->guardCanGenerateBracket($t);
    }
}
