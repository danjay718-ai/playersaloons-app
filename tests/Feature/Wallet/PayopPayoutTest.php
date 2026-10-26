<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Livewire\Admin\SystemSettingsAdmin;
use App\Livewire\Wallet\WalletDashboard;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Wallet\Actions\ApproveWithdrawalAction;
use App\Modules\Wallet\Actions\ProcessWithdrawalAction;
use App\Modules\Wallet\Actions\RequestWithdrawalAction;
use App\Modules\Wallet\Actions\ReviewWithdrawalAction;
use App\Modules\Wallet\Actions\SyncPayopPayoutAction;
use App\Modules\Wallet\Exceptions\PayoutSubmissionRejectedException;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use App\Shared\Enums\WithdrawalStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayopPayoutTest extends TestCase
{
    use RefreshDatabase;

    private string $keypair;

    private User $reviewer;

    private string $reference;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->keypair = sodium_crypto_box_keypair();
        $this->reference = Str::uuid()->toString();
        $this->reviewer = $this->user('FINANCE_OPERATOR');
        config(['app.currency' => 'EUR', 'services.payop.payout_token' => 'payout-jwt',
            'services.payop.payout_certificate' => base64_encode(sodium_crypto_box_publickey($this->keypair))]);
        $this->setting('payments.payout_provider', 'payop');
        Http::preventStrayRequests();
        $this->fakeApi();
    }

    public function test_admin_can_upload_certificate_and_enable_payouts_independently_of_deposits(): void
    {
        Storage::fake('local');
        $admin = $this->user('SUPER_ADMIN');
        $certificate = UploadedFile::fake()->createWithContent('x25519.pub', sodium_crypto_box_publickey($this->keypair));
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)->set('paymentsEnabled', false)
            ->set('payoutProvider', 'payop')->set('paymentCredentials.payop_payout_token', 'saved-payout-token')
            ->set('payopPayoutCertificate', $certificate)->call('savePaymentSettings')->assertHasNoErrors()
            ->assertSet('payopPayoutCertificate', null)->assertSet('paymentCredentials', [])->assertDontSee('saved-payout-token');
        $this->assertDatabaseHas('system_settings', ['key' => 'payments.provider', 'value' => 'stripe']);
        $this->assertDatabaseHas('system_settings', ['key' => 'payments.payout_provider', 'value' => 'payop']);
        self::assertNotSame('saved-payout-token', SystemSetting::query()->where('key', 'payments.payop.payout_token')->value('value'));
    }

    public function test_admin_cannot_enable_unavailable_bank_payouts_or_invalid_certificates(): void
    {
        $admin = $this->user('SUPER_ADMIN');
        $this->resetHttp();
        Http::fake(['api.payop.com/*' => Http::response(['status' => 1, 'data' => [['type' => 15, 'currencies' => ['EUR']]]])]);
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)->set('paymentsEnabled', false)
            ->set('payoutProvider', 'payop')->call('savePaymentSettings')->assertHasErrors('payoutProvider');
        Storage::fake('local');
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)->set('paymentsEnabled', false)
            ->set('payopPayoutCertificate', UploadedFile::fake()->createWithContent('bad.pub', 'invalid'))
            ->call('savePaymentSettings')->assertHasErrors('payopPayoutCertificate');
        Http::assertNotSent(fn (Request $r): bool => $r->method() === 'POST');
    }

    public function test_player_submits_encrypted_bank_details_and_the_gateway_is_snapshotted(): void
    {
        $user = $this->player();
        Livewire::actingAs($user)->test(WalletDashboard::class)
            ->assertSee('Bank account IBAN')->set('amount', '50.00')->set('payoutName', 'Jane Player')
            ->set('payoutIban', 'GB82 WEST 1234 5698 7654 32')->call('withdraw')->assertHasNoErrors()
            ->assertSee('Recent withdrawal requests')->assertSet('payoutIban', '');
        $withdrawal = Withdrawal::query()->sole();
        self::assertSame('payop', $withdrawal->payout_provider);
        self::assertSame('EUR', $withdrawal->payout_currency);
        self::assertSame('GB82WEST12345698765432', $withdrawal->payout_recipient['iban']);
        self::assertStringNotContainsString('GB82WEST', $withdrawal->getRawOriginal('payout_recipient'));
        self::assertArrayNotHasKey('payout_recipient', $withdrawal->toArray());
        self::assertSame('100.00', $user->wallet->fresh()->cached_balance);
        $this->setting('payments.payout_provider', 'manual');
        self::assertSame('payop', $withdrawal->fresh()->payout_provider);
    }

    public function test_invalid_iban_is_rejected_without_a_withdrawal_or_gateway_request(): void
    {
        $user = $this->player();
        $this->resetHttp();
        Http::fake();
        Livewire::actingAs($user)->test(WalletDashboard::class)->set('amount', '50')
            ->set('payoutName', 'Jane')->set('payoutIban', 'GB82WEST12345698765433')->call('withdraw')->assertHasErrors('iban');
        $this->assertDatabaseCount('withdrawals', 0);
        Http::assertNothingSent();
    }

    public function test_api_withdrawal_validates_bank_details_and_returns_gateway_tracking(): void
    {
        $user = $this->player();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/withdraw', ['amount' => '50.00'])->assertUnprocessable()->assertJsonValidationErrors(['iban', 'name']);
        $this->postJson('/api/v1/wallet/withdraw', ['amount' => '50.00', ...$this->recipient()])
            ->assertCreated()->assertJsonPath('data.payout_provider', 'payop')->assertJsonPath('data.currency', 'EUR');
        $this->assertDatabaseCount('withdrawals', 1);
    }

    public function test_two_pending_requests_cannot_reserve_more_than_the_wallet_balance(): void
    {
        $user = $this->player();
        $first = app(RequestWithdrawalAction::class)->execute($user, '80.00', $this->recipient());
        $second = app(RequestWithdrawalAction::class)->execute($user, '80.00', $this->recipient());
        foreach ([$first, $second] as $withdrawal) {
            app(ReviewWithdrawalAction::class)->execute($withdrawal, $this->reviewer);
        }
        app(ApproveWithdrawalAction::class)->execute($first, $this->reviewer);
        $this->expectException(LogicException::class);
        try {
            app(ApproveWithdrawalAction::class)->execute($second, $this->reviewer);
        } finally {
            self::assertSame('20.00', $user->wallet->fresh()->cached_balance);
            self::assertSame(WithdrawalStatus::UNDER_REVIEW, $second->fresh()->status);
            $this->assertDatabaseCount('ledger_entries', 1);
        }
    }

    public function test_approval_reserves_funds_synchronously_even_when_queued_listeners_have_not_run(): void
    {
        Queue::fake();
        $withdrawal = $this->approved();
        self::assertSame('50.00', $withdrawal->wallet->fresh()->cached_balance);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseHas('ledger_entries', ['type' => 'WITHDRAWAL', 'amount' => '-50.00']);
    }

    public function test_approved_payout_is_encrypted_submitted_once_and_not_marked_paid_on_submission(): void
    {
        $withdrawal = $this->approved();
        $this->setting('payments.payout_provider', 'manual');
        app(ProcessWithdrawalAction::class)->execute($withdrawal, $this->reviewer);
        app(ProcessWithdrawalAction::class)->execute($withdrawal->fresh(), $this->reviewer);
        self::assertSame(WithdrawalStatus::APPROVED, $withdrawal->fresh()->status);
        self::assertSame('pending', $withdrawal->fresh()->payout_status);
        self::assertSame($this->reference, $withdrawal->fresh()->payout_reference);
        self::assertNull($withdrawal->fresh()->processed_at);
        Http::assertSent(function (Request $r) use ($withdrawal): bool {
            if ($r->method() !== 'POST') {
                return false;
            }
            self::assertTrue($r->hasHeader('Authorization', 'Bearer payout-jwt'));
            self::assertTrue($r->hasHeader('idempotency-key', $withdrawal->uuid));
            $decoded = json_decode(sodium_crypto_box_seal_open(base64_decode($r['data']), $this->keypair), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($withdrawal->uuid, $decoded[0]['clientId']);
            self::assertSame(20, $decoded[0]['method']);
            self::assertSame(1, $decoded[0]['type']);
            self::assertSame('50.00', $decoded[0]['amount']);
            self::assertSame('EUR', $decoded[0]['currency']);
            self::assertSame('GB82WEST12345698765432', $decoded[0]['additionalData']['beneficiary']['account']);

            return true;
        });
        self::assertCount(1, Http::recorded(fn (Request $r): bool => $r->method() === 'POST'));
    }

    public function test_verified_acceptance_updates_request_and_duplicates_do_not_debit_again(): void
    {
        $withdrawal = $this->submitted();
        $this->fakeApi($withdrawal, 2);
        $payload = ['transaction' => ['withdrawalId' => $this->reference, 'state' => 3]];
        $this->postJson('/payop/payout-webhook', $payload)->assertOk();
        $this->postJson('/payop/payout-webhook', $payload)->assertOk();
        self::assertSame(WithdrawalStatus::PROCESSED, $withdrawal->fresh()->status);
        self::assertNotNull($withdrawal->fresh()->processed_at);
        self::assertSame('50.00', $withdrawal->wallet->fresh()->cached_balance);
        $this->assertDatabaseCount('ledger_entries', 1);
    }

    public function test_verified_rejection_refunds_once_and_a_new_request_can_use_returned_funds(): void
    {
        $withdrawal = $this->submitted();
        $this->fakeApi($withdrawal, 3);
        app(SyncPayopPayoutAction::class)->execute($this->reference);
        app(SyncPayopPayoutAction::class)->execute($this->reference);
        self::assertSame(WithdrawalStatus::FAILED, $withdrawal->fresh()->status);
        self::assertSame('rejected', $withdrawal->fresh()->payout_status);
        self::assertSame('100.00', $withdrawal->wallet->fresh()->cached_balance);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertDatabaseHas('ledger_entries', ['type' => 'REFUND', 'amount' => '50.00']);
        $new = app(RequestWithdrawalAction::class)->execute($withdrawal->user, '100.00', $this->recipient());
        self::assertSame(WithdrawalStatus::PENDING, $new->status);
    }

    public function test_untrusted_paid_notification_does_not_finalize_a_pending_remote_payout(): void
    {
        $withdrawal = $this->submitted();
        $this->fakeApi($withdrawal, 4);
        $this->postJson('/payop/payout-webhook', ['transaction' => ['withdrawalId' => $this->reference, 'state' => 2]])->assertOk();
        self::assertSame(WithdrawalStatus::APPROVED, $withdrawal->fresh()->status);
        self::assertNull($withdrawal->fresh()->processed_at);
    }

    #[DataProvider('definitiveRefusals')]
    public function test_definitive_submission_refusal_refunds_the_reserved_amount(int $httpStatus): void
    {
        $withdrawal = $this->approved();
        $this->resetHttp();
        Http::fake(['api.payop.com/v1/withdrawals/create-mass' => Http::response(['message' => 'Refused'], $httpStatus),
            'api.payop.com/*' => Http::response($this->methods())]);
        $this->expectException(PayoutSubmissionRejectedException::class);
        try {
            app(ProcessWithdrawalAction::class)->execute($withdrawal, $this->reviewer);
        } finally {
            self::assertSame(WithdrawalStatus::FAILED, $withdrawal->fresh()->status);
            self::assertSame('100.00', $withdrawal->wallet->fresh()->cached_balance);
            $this->assertDatabaseCount('ledger_entries', 2);
        }
    }

    public static function definitiveRefusals(): array
    {
        return [[400], [401], [403], [422]];
    }

    public function test_ambiguous_server_failure_keeps_funds_reserved(): void
    {
        $withdrawal = $this->approved();
        $this->resetHttp();
        Http::fake(['api.payop.com/v1/withdrawals/create-mass' => Http::response([], 500),
            'api.payop.com/*' => Http::response($this->methods())]);
        $this->expectException(LogicException::class);
        try {
            app(ProcessWithdrawalAction::class)->execute($withdrawal, $this->reviewer);
        } finally {
            self::assertSame('unknown', $withdrawal->fresh()->payout_status);
            self::assertSame('50.00', $withdrawal->wallet->fresh()->cached_balance);
        }
    }

    public function test_changed_final_status_requires_reconciliation_instead_of_recrediting(): void
    {
        $withdrawal = $this->submitted();
        $this->fakeApi($withdrawal, 2);
        app(SyncPayopPayoutAction::class)->execute($this->reference);
        $this->fakeApi($withdrawal, 3);
        $this->expectException(LogicException::class);
        try {
            app(SyncPayopPayoutAction::class)->execute($this->reference);
        } finally {
            self::assertSame(WithdrawalStatus::PROCESSED, $withdrawal->fresh()->status);
            self::assertSame('50.00', $withdrawal->wallet->fresh()->cached_balance);
        }
    }

    public function test_reconciliation_cannot_bind_a_reference_to_a_different_selected_withdrawal(): void
    {
        $withdrawal = $this->submitted();
        $this->fakeApi($withdrawal, 2);
        $this->expectException(LogicException::class);
        app(SyncPayopPayoutAction::class)->execute($this->reference, Str::uuid()->toString());
    }

    public function test_timeout_keeps_funds_reserved_blocks_resend_and_can_recover_from_verified_callback(): void
    {
        $withdrawal = $this->approved();
        $this->resetHttp();
        Http::fake(['api.payop.com/v1/withdrawals/create-mass' => fn () => throw new ConnectionException('Timed out'),
            'api.payop.com/*' => Http::response($this->methods())]);
        try {
            app(ProcessWithdrawalAction::class)->execute($withdrawal, $this->reviewer);
            self::fail('Expected an uncertain submission.');
        } catch (ConnectionException) {
            self::assertSame('unknown', $withdrawal->fresh()->payout_status);
            self::assertSame('50.00', $withdrawal->wallet->fresh()->cached_balance);
        }
        $this->fakeApi($withdrawal, 2);
        app(ProcessWithdrawalAction::class)->execute($withdrawal->fresh(), $this->reviewer);
        Http::assertNotSent(fn (Request $r): bool => $r->method() === 'POST');
        $this->postJson('/payop/payout-webhook', ['transaction' => ['withdrawalId' => $this->reference,
            'metadata' => ['internalWithdrawalId' => $withdrawal->uuid]]])->assertOk();
        self::assertSame(WithdrawalStatus::PROCESSED, $withdrawal->fresh()->status);
        self::assertSame($this->reference, $withdrawal->fresh()->payout_reference);
    }

    #[DataProvider('mismatches')]
    public function test_mismatched_remote_payout_cannot_finalize_or_refund(string $key, mixed $value): void
    {
        $withdrawal = $this->submitted();
        $this->fakeApi($withdrawal, 2, [$key => $value]);
        $this->expectException(LogicException::class);
        try {
            app(SyncPayopPayoutAction::class)->execute($this->reference);
        } finally {
            self::assertSame(WithdrawalStatus::APPROVED, $withdrawal->fresh()->status);
            self::assertSame('50.00', $withdrawal->wallet->fresh()->cached_balance);
        }
    }

    public static function mismatches(): array
    {
        return ['amount' => ['amount', '49.00'], 'currency' => ['currency', 'USD'], 'order' => ['metadata', ['internalWithdrawalId' => 'wrong']], 'state' => ['status', 99]];
    }

    public function test_requestor_cannot_submit_their_own_approved_payout(): void
    {
        $withdrawal = $this->approved();
        $withdrawal->user->assignRole('FINANCE_OPERATOR');
        $this->expectException(AuthorizationException::class);
        app(ProcessWithdrawalAction::class)->execute($withdrawal, $withdrawal->user);
    }

    public function test_scheduler_reconciles_pending_payout_without_webhook(): void
    {
        $withdrawal = $this->submitted();
        $this->fakeApi($withdrawal, 2);
        $this->artisan('payments:reconcile-payop-payouts')->assertSuccessful();
        self::assertSame(WithdrawalStatus::PROCESSED, $withdrawal->fresh()->status);
    }

    public function test_unknown_callback_is_ignored_and_malformed_reference_is_rejected(): void
    {
        $this->resetHttp();
        Http::fake();
        $this->postJson('/payop/payout-webhook', ['transaction' => ['withdrawalId' => $this->reference]])->assertOk();
        $this->postJson('/payop/payout-webhook', ['transaction' => ['withdrawalId' => 'bad']])->assertUnprocessable();
        Http::assertNothingSent();
    }

    private function resetHttp(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    private function methods(): array
    {
        return ['status' => 1, 'data' => [['type' => 20, 'currencies' => ['EUR', 'GBP']]]];
    }

    private function fakeApi(?Withdrawal $withdrawal = null, int $state = 1, array $overrides = []): void
    {
        $data = $withdrawal ? array_replace(['identifier' => $this->reference, 'status' => $state, 'amount' => $withdrawal->amount,
            'currency' => $withdrawal->payout_currency, 'metadata' => ['internalWithdrawalId' => $withdrawal->uuid]], $overrides) : [];
        $this->resetHttp();
        Http::fake([
            'api.payop.com/v1/withdrawals/create-mass' => Http::response(['status' => 1, 'data' => [['id' => $this->reference]]]),
            'api.payop.com/v1/withdrawals/user-withdrawals*' => Http::response(['status' => 1, 'data' => [$data]]),
            'api.payop.com/*' => Http::response($this->methods()),
        ]);
    }

    private function recipient(): array
    {
        return ['name' => 'Jane Player', 'iban' => 'GB82WEST12345698765432'];
    }

    private function approved(): Withdrawal
    {
        $withdrawal = app(RequestWithdrawalAction::class)->execute($this->player(), '50.00', $this->recipient());
        app(ReviewWithdrawalAction::class)->execute($withdrawal, $this->reviewer);
        app(ApproveWithdrawalAction::class)->execute($withdrawal, $this->reviewer);

        return $withdrawal->fresh();
    }

    private function submitted(): Withdrawal
    {
        $withdrawal = $this->approved();
        app(ProcessWithdrawalAction::class)->execute($withdrawal, $this->reviewer);

        return $withdrawal->fresh();
    }

    private function player(): User
    {
        $user = $this->user('PLAYER');
        Wallet::query()->create(['uuid' => Str::uuid()->toString(), 'user_id' => $user->id, 'cached_balance' => '100.00', 'status' => WalletStatus::ACTIVE]);
        KycSubmission::query()->create(['uuid' => Str::uuid()->toString(), 'user_id' => $user->id, 'status' => KycStatus::APPROVED, 'document_type' => 'passport', 'document_paths' => ['test.png']]);

        return $user;
    }

    private function user(string $role): User
    {
        $id = Str::uuid()->toString();
        $user = User::query()->create(['uuid' => $id, 'username' => 'payout-'.substr($id, 0, 8), 'email' => $id.'@example.com', 'email_verified_at' => now(), 'password' => bcrypt('password'), 'status' => UserStatus::ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    private function setting(string $key, string $value): void
    {
        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
