<?php

declare(strict_types=1);

namespace Tests\Unit\StateMachines;

use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\StateMachines\WalletStateMachine;
use App\Shared\Enums\WalletStatus;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class WalletStateMachineTest extends TestCase
{
    private WalletStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new WalletStateMachine;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeWallet(WalletStatus $status): Wallet
    {
        /** @var Wallet&MockInterface $wallet */
        $wallet = Mockery::mock(Wallet::class)->makePartial();
        $wallet->status = $status;
        $wallet->shouldReceive('save')->once();

        return $wallet;
    }

    // ------------------------------------------------------------------ //
    // Valid transitions
    // ------------------------------------------------------------------ //

    public function test_active_to_suspended(): void
    {
        $wallet = $this->makeWallet(WalletStatus::ACTIVE);
        $this->machine->transition($wallet, WalletStatus::SUSPENDED);

        $this->assertSame(WalletStatus::SUSPENDED, $wallet->status);
    }

    public function test_active_to_frozen(): void
    {
        $wallet = $this->makeWallet(WalletStatus::ACTIVE);
        $this->machine->transition($wallet, WalletStatus::FROZEN);

        $this->assertSame(WalletStatus::FROZEN, $wallet->status);
    }

    public function test_suspended_to_frozen(): void
    {
        $wallet = $this->makeWallet(WalletStatus::SUSPENDED);
        $this->machine->transition($wallet, WalletStatus::FROZEN);

        $this->assertSame(WalletStatus::FROZEN, $wallet->status);
    }

    public function test_frozen_to_active_with_super_admin_succeeds(): void
    {
        /** @var Wallet&MockInterface $wallet */
        $wallet = Mockery::mock(Wallet::class)->makePartial();
        $wallet->status = WalletStatus::FROZEN;
        $wallet->shouldReceive('save')->once();

        /** @var User&MockInterface $actor */
        $actor = Mockery::mock(User::class);
        $actor->shouldReceive('hasRole')->with('super_admin')->andReturn(true);
        $actor->shouldReceive('hasRole')->with('SUPER_ADMIN')->andReturn(true);

        $this->machine->transition($wallet, WalletStatus::ACTIVE, $actor);

        $this->assertSame(WalletStatus::ACTIVE, $wallet->status);
    }

    public function test_frozen_to_active_without_super_admin_throws_logic_exception(): void
    {
        /** @var Wallet&MockInterface $wallet */
        $wallet = Mockery::mock(Wallet::class)->makePartial();
        $wallet->status = WalletStatus::FROZEN;
        $wallet->shouldNotReceive('save');

        /** @var User&MockInterface $actor */
        $actor = Mockery::mock(User::class);
        $actor->shouldReceive('hasRole')->with('super_admin')->andReturn(false);
        $actor->shouldReceive('hasRole')->with('SUPER_ADMIN')->andReturn(false);

        $this->expectException(\LogicException::class);

        $this->machine->transition($wallet, WalletStatus::ACTIVE, $actor);
    }

    // ------------------------------------------------------------------ //
    // Invalid transitions
    // ------------------------------------------------------------------ //

    public function test_active_to_active_throws_invalid_transition(): void
    {
        /** @var Wallet&MockInterface $wallet */
        $wallet = Mockery::mock(Wallet::class)->makePartial();
        $wallet->status = WalletStatus::ACTIVE;
        $wallet->shouldNotReceive('save');

        $this->expectException(InvalidStateTransitionException::class);

        $this->machine->transition($wallet, WalletStatus::ACTIVE);
    }

    public function test_frozen_to_suspended_throws_invalid_transition(): void
    {
        /** @var Wallet&MockInterface $wallet */
        $wallet = Mockery::mock(Wallet::class)->makePartial();
        $wallet->status = WalletStatus::FROZEN;
        $wallet->shouldNotReceive('save');

        $this->expectException(InvalidStateTransitionException::class);

        $this->machine->transition($wallet, WalletStatus::SUSPENDED);
    }
}
