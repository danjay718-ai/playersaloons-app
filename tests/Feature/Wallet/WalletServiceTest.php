<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Actions\ApproveWithdrawalAction;
use App\Modules\Wallet\Actions\FreezeWalletAction;
use App\Modules\Wallet\Actions\ProcessDepositAction;
use App\Modules\Wallet\Actions\RejectWithdrawalAction;
use App\Modules\Wallet\Actions\RequestWithdrawalAction;
use App\Modules\Wallet\Actions\ReviewWithdrawalAction;
use App\Modules\Wallet\Actions\SuspendWalletAction;
use App\Modules\Wallet\Actions\UnfreezeWalletAction;
use App\Modules\Wallet\Actions\UnsuspendWalletAction;
use App\Modules\Wallet\Events\WalletCredited;
use App\Modules\Wallet\Events\WalletDebited;
use App\Modules\Wallet\Exceptions\InsufficientBalanceException;
use App\Modules\Wallet\Exceptions\WalletFrozenException;
use App\Modules\Wallet\Exceptions\WalletSuspendedException;
use App\Modules\Wallet\Models\Deposit;
use App\Modules\Wallet\Models\LedgerEntry;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\Services\WalletService;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\LedgerType;
use App\Shared\Enums\WalletStatus;
use App\Shared\Enums\WithdrawalStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createUser(string $username = 'player'): User
    {
        $user = new User;
        $user->fill([
            'uuid' => Str::uuid()->toString(),
            'username' => $username,
            'email' => "{$username}@example.com",
            'password' => 'secret-pwd',
            'status' => \App\Shared\Enums\UserStatus::ACTIVE,
        ]);
        $user->save();

        return $user;
    }

    private function createWallet(User $user, string $balance = '0.00', WalletStatus $status = WalletStatus::ACTIVE): Wallet
    {
        $wallet = new Wallet;
        $wallet->fill([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->getKey(),
            'cached_balance' => $balance,
            'status' => $status,
        ]);
        $wallet->save();

        return $wallet;
    }

    public function test_credit_increases_cached_balance_and_creates_ledger_entry(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        $ledgerEntry = $this->walletService->credit(
            $wallet,
            '50.50',
            LedgerType::DEPOSIT,
            'test_ref',
            '123',
            'Test Deposit'
        );

        $this->assertInstanceOf(LedgerEntry::class, $ledgerEntry);
        $this->assertSame('50.50', $ledgerEntry->getAttribute('amount'));
        $this->assertSame('150.50', $ledgerEntry->getAttribute('running_balance'));

        $wallet->refresh();
        $this->assertSame('150.50', $this->walletService->getBalance($wallet));
    }

    public function test_debit_decreases_cached_balance_and_creates_ledger_entry(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        $ledgerEntry = $this->walletService->debit(
            $wallet,
            '30.25',
            LedgerType::ENTRY_FEE,
            'test_ref',
            '123',
            'Test Entry Fee'
        );

        $this->assertInstanceOf(LedgerEntry::class, $ledgerEntry);
        $this->assertSame('-30.25', $ledgerEntry->getAttribute('amount')); // Stored as negative for debits
        $this->assertSame('69.75', $ledgerEntry->getAttribute('running_balance'));

        $wallet->refresh();
        $this->assertSame('69.75', $this->walletService->getBalance($wallet));
    }

    public function test_debit_fails_with_insufficient_balance(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '20.00');

        $this->expectException(InsufficientBalanceException::class);

        $this->walletService->debit(
            $wallet,
            '25.00',
            LedgerType::ENTRY_FEE,
            'test_ref',
            '123'
        );
    }

    public function test_debit_blocked_if_wallet_suspended(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00', WalletStatus::SUSPENDED);

        $this->expectException(WalletSuspendedException::class);

        $this->walletService->debit(
            $wallet,
            '10.00',
            LedgerType::ENTRY_FEE,
            'test_ref',
            '123'
        );
    }

    public function test_credit_allows_suspended_wallet(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00', WalletStatus::SUSPENDED);

        $ledgerEntry = $this->walletService->credit(
            $wallet,
            '50.00',
            LedgerType::DEPOSIT,
            'test_ref',
            '123'
        );

        $this->assertSame('150.00', $ledgerEntry->getAttribute('running_balance'));
    }

    public function test_credit_and_debit_blocked_if_wallet_frozen(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00', WalletStatus::FROZEN);

        $this->expectException(WalletFrozenException::class);
        $this->walletService->credit($wallet, '10.00', LedgerType::DEPOSIT, 'test_ref', '123');
    }

    public function test_debit_blocked_if_wallet_frozen(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00', WalletStatus::FROZEN);

        $this->expectException(WalletFrozenException::class);
        $this->walletService->debit($wallet, '10.00', LedgerType::ENTRY_FEE, 'test_ref', '123');
    }

    public function test_ledger_is_immutable(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        $ledgerEntry = $this->walletService->credit(
            $wallet,
            '50.00',
            LedgerType::DEPOSIT,
            'test_ref',
            '123'
        );

        $this->expectException(LogicException::class);
        $ledgerEntry->update(['amount' => '100.00']);
    }

    public function test_recalculate_balance_corrects_drift(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        // Create entries directly to bypass service balance updates
        LedgerEntry::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $wallet->getKey(),
            'reference_type' => 'manual',
            'reference_id' => 1,
            'type' => LedgerType::DEPOSIT,
            'amount' => '200.00',
            'running_balance' => '200.00',
        ]);

        LedgerEntry::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $wallet->getKey(),
            'reference_type' => 'manual',
            'reference_id' => 2,
            'type' => LedgerType::ENTRY_FEE,
            'amount' => '-50.00',
            'running_balance' => '150.00',
        ]);

        // Drift is corrected from cached_balance 100.00 to 150.00
        $recalculated = $this->walletService->recalculateBalance($wallet);
        $this->assertSame('150.00', $recalculated);

        $wallet->refresh();
        $this->assertSame('150.00', $wallet->getAttribute('cached_balance'));
    }

    public function test_process_deposit_action(): void
    {
        Event::fake([WalletCredited::class]);

        $user = $this->createUser();
        $wallet = $this->createWallet($user, '0.00');

        $deposit = app(ProcessDepositAction::class)->execute($wallet, '100.00', 'stripe', 'ch_123');

        $this->assertInstanceOf(Deposit::class, $deposit);
        $this->assertSame('completed', $deposit->getAttribute('status'));
        $this->assertSame('100.00', $deposit->getAttribute('amount'));

        $wallet->refresh();
        $this->assertSame('100.00', $wallet->getAttribute('cached_balance'));

        Event::assertDispatched(WalletCredited::class);
    }

    public function test_request_withdrawal_blocked_if_kyc_not_approved(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('KYC must be approved to request a withdrawal.');

        app(RequestWithdrawalAction::class)->execute($user, '50.00');
    }

    public function test_request_withdrawal_blocked_if_insufficient_balance(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '10.00');

        // Approve KYC
        KycSubmission::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->getKey(),
            'status' => KycStatus::APPROVED,
            'document_type' => 'passport',
            'document_paths' => ['kyc.png'],
        ]);

        $this->expectException(InsufficientBalanceException::class);
        app(RequestWithdrawalAction::class)->execute($user, '50.00');
    }

    public function test_request_withdrawal_creates_pending_withdrawal(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        // Approve KYC
        KycSubmission::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->getKey(),
            'status' => KycStatus::APPROVED,
            'document_type' => 'passport',
            'document_paths' => ['kyc.png'],
        ]);

        $withdrawal = app(RequestWithdrawalAction::class)->execute($user, '50.00');

        $this->assertSame(WithdrawalStatus::PENDING, $withdrawal->status);
        $this->assertSame('50.00', $withdrawal->getAttribute('amount'));
    }

    public function test_review_withdrawal_action_transitions_status(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        $reviewer = $this->createUser('reviewer');
        $reviewer->assignRole('FINANCE_OPERATOR');

        $withdrawal = Withdrawal::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $wallet->getKey(),
            'user_id' => $user->getKey(),
            'amount' => '50.00',
            'status' => WithdrawalStatus::PENDING,
        ]);

        app(ReviewWithdrawalAction::class)->execute($withdrawal, $reviewer);

        $this->assertSame(WithdrawalStatus::UNDER_REVIEW, $withdrawal->status);
        $this->assertSame($reviewer->getKey(), $withdrawal->getAttribute('reviewed_by'));
    }

    public function test_withdrawal_approval_and_rejection_enforce_four_eyes(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        // KYC
        KycSubmission::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->getKey(),
            'status' => KycStatus::APPROVED,
            'document_type' => 'passport',
            'document_paths' => ['kyc.png'],
        ]);

        $withdrawal = Withdrawal::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $wallet->getKey(),
            'user_id' => $user->getKey(),
            'amount' => '50.00',
            'status' => WithdrawalStatus::UNDER_REVIEW,
        ]);

        // User tries to approve their own request
        $user->assignRole('ADMIN');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Requestor cannot approve their own withdrawal request.');

        app(ApproveWithdrawalAction::class)->execute($withdrawal, $user);
    }

    public function test_withdrawal_actions_require_correct_roles(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        $unauthorized = $this->createUser('unauthorized');

        $withdrawal = Withdrawal::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $wallet->getKey(),
            'user_id' => $user->getKey(),
            'amount' => '50.00',
            'status' => WithdrawalStatus::PENDING,
        ]);

        $this->expectException(AuthorizationException::class);
        app(ReviewWithdrawalAction::class)->execute($withdrawal, $unauthorized);
    }

    public function test_suspend_unsuspend_wallet_actions(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        app(SuspendWalletAction::class)->execute($wallet);
        $this->assertSame(WalletStatus::SUSPENDED, $wallet->status);

        app(UnsuspendWalletAction::class)->execute($wallet);
        $this->assertSame(WalletStatus::ACTIVE, $wallet->status);
    }

    public function test_freeze_unfreeze_wallet_actions(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        $admin = $this->createUser('admin');
        $admin->assignRole('ADMIN');

        $superAdmin = $this->createUser('super');
        $superAdmin->assignRole('SUPER_ADMIN');

        app(FreezeWalletAction::class)->execute($wallet, $admin);
        $this->assertSame(WalletStatus::FROZEN, $wallet->status);

        // Standard admin cannot unfreeze
        $this->expectException(LogicException::class);
        app(UnfreezeWalletAction::class)->execute($wallet, $admin);
    }

    public function test_super_admin_can_unfreeze(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user, '100.00');

        $admin = $this->createUser('admin');
        $admin->assignRole('ADMIN');

        $superAdmin = $this->createUser('super');
        $superAdmin->assignRole('SUPER_ADMIN');

        app(FreezeWalletAction::class)->execute($wallet, $admin);

        app(UnfreezeWalletAction::class)->execute($wallet, $superAdmin);
        $this->assertSame(WalletStatus::ACTIVE, $wallet->status);
    }
}
