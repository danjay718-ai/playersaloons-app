<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\DepositAdmin;
use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Actions\ProcessDepositAction;
use App\Modules\Wallet\Models\Deposit;
use App\Modules\Wallet\Models\PaymentCheckout;
use App\Modules\Wallet\Models\Wallet;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Database\Seeders\PlatformSystemUserSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DepositAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $financeOperator;

    private User $kycReviewer;

    private User $player;

    private Wallet $playerWallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);

        $this->admin = $this->createUserWithRole('ADMIN', 'admin@example.com');
        $this->financeOperator = $this->createUserWithRole('FINANCE_OPERATOR', 'finance@example.com');
        $this->kycReviewer = $this->createUserWithRole('KYC_REVIEWER', 'kyc@example.com');
        $this->player = $this->createUserWithRole('PLAYER', 'player@example.com');
        $this->playerWallet = Wallet::query()->where('user_id', $this->player->id)->firstOrFail();
    }

    private function createUserWithRole(string $role, string $email): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => $email,
            'username' => explode('@', $email)[0],
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'status' => UserStatus::ACTIVE,
        ]);

        $user->assignRole($role);

        Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'cached_balance' => '0.00',
            'status' => WalletStatus::ACTIVE,
        ]);

        return $user;
    }

    public function test_unauthorized_users_cannot_access_admin_deposits(): void
    {
        $this->actingAs($this->player)
            ->get(route('admin.deposits'))
            ->assertForbidden();

        $this->actingAs($this->kycReviewer)
            ->get(route('admin.deposits'))
            ->assertForbidden();
    }

    public function test_authorized_staff_can_view_deposit_history_and_accurate_kpi_totals(): void
    {
        $processDeposit = app(ProcessDepositAction::class);

        // Net 50.00 + Fee 2.50 => Gross 52.50
        $processDeposit->execute($this->playerWallet, '50.00', 'stripe', 'cs_live_1001', '2.50');

        // Net 100.00 + Fee 5.00 => Gross 105.00
        $processDeposit->execute($this->playerWallet, '100.00', 'payop', 'inv_live_2002', '5.00');

        // Pending deposit should not count toward completed KPIs
        Deposit::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $this->playerWallet->id,
            'amount' => '25.00',
            'fee_amount' => '1.00',
            'provider' => 'stripe',
            'provider_reference' => 'cs_pending_3003',
            'status' => 'pending',
        ]);

        // Failed deposit
        Deposit::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $this->playerWallet->id,
            'amount' => '10.00',
            'fee_amount' => '0.50',
            'provider' => 'payop',
            'provider_reference' => 'inv_failed_4004',
            'status' => 'failed',
        ]);

        $this->actingAs($this->financeOperator)
            ->get(route('admin.deposits'))
            ->assertOk()
            ->assertSee('Deposit History')
            ->assertSee('cs_live_1001')
            ->assertSee('inv_live_2002');

        Livewire::actingAs($this->admin)
            ->test(DepositAdmin::class)
            ->assertViewHas('summary', function (array $summary): bool {
                return $summary['completed_count'] === 2
                    && $summary['net_credited'] === '150.00'
                    && $summary['fees_collected'] === '7.50'
                    && $summary['gross_charged'] === '157.50'
                    && $summary['pending_count'] === 1
                    && $summary['failed_count'] === 1;
            });
    }

    public function test_filters_and_search_narrow_deposits_and_default_window_bounds_old_records(): void
    {
        $secondPlayer = $this->createUserWithRole('PLAYER', 'rival@example.com');
        $secondWallet = Wallet::query()->where('user_id', $secondPlayer->id)->firstOrFail();

        $recentStripe = Deposit::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $this->playerWallet->id,
            'amount' => '40.00',
            'fee_amount' => '2.00',
            'provider' => 'stripe',
            'provider_reference' => 'cs_recent_abc',
            'status' => 'completed',
        ]);

        $recentPayop = Deposit::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $secondWallet->id,
            'amount' => '60.00',
            'fee_amount' => '3.00',
            'provider' => 'payop',
            'provider_reference' => 'inv_recent_xyz',
            'status' => 'pending',
        ]);

        $oldDeposit = Deposit::query()->create([
            'uuid' => Str::uuid()->toString(),
            'wallet_id' => $this->playerWallet->id,
            'amount' => '99.00',
            'fee_amount' => '4.00',
            'provider' => 'stripe',
            'provider_reference' => 'cs_old_90days',
            'status' => 'completed',
        ]);
        Deposit::query()->whereKey($oldDeposit->id)->update(['created_at' => now()->subDays(90)]);

        // Default mount applies last 30 days filter, hiding 90-day-old deposit
        Livewire::actingAs($this->admin)
            ->test(DepositAdmin::class)
            ->assertSee('cs_recent_abc')
            ->assertSee('inv_recent_xyz')
            ->assertDontSee('cs_old_90days')
            // Filter by provider
            ->set('providerFilter', 'payop')
            ->assertSee('inv_recent_xyz')
            ->assertDontSee('cs_recent_abc')
            // Filter by status
            ->set('providerFilter', '')
            ->set('statusFilter', 'completed')
            ->assertSee('cs_recent_abc')
            ->assertDontSee('inv_recent_xyz')
            // Search by username / email / UUID / reference
            ->set('statusFilter', '')
            ->set('search', 'rival@example.com')
            ->assertSee('inv_recent_xyz')
            ->assertDontSee('cs_recent_abc')
            ->set('search', $recentStripe->uuid)
            ->assertSee('cs_recent_abc')
            ->assertDontSee('inv_recent_xyz')
            // Clearing filters exposes older records too
            ->call('clearFilters')
            ->assertSee('cs_old_90days')
            ->assertSee('cs_recent_abc')
            ->assertSee('inv_recent_xyz');
    }

    public function test_detail_modal_shows_ledger_checkout_and_handles_soft_deleted_users(): void
    {
        $deposit = app(ProcessDepositAction::class)->execute(
            $this->playerWallet,
            '75.00',
            'stripe',
            'cs_detail_555',
            '3.75'
        );

        $checkout = PaymentCheckout::query()->create([
            'id' => Str::uuid()->toString(),
            'wallet_id' => $this->playerWallet->id,
            'provider' => 'stripe',
            'provider_reference' => 'cs_detail_555',
            'currency' => 'USD',
            'amount' => '75.00',
            'fee_amount' => '3.75',
            'total' => '78.75',
            'completed_at' => now(),
        ]);

        // Soft-delete the player to ensure audit view remains intact
        $this->player->delete();

        Livewire::actingAs($this->admin)
            ->test(DepositAdmin::class)
            ->assertSee($this->player->username)
            ->assertSee('Deleted')
            ->call('selectDeposit', $deposit->id)
            ->assertSet('showDetailModal', true)
            ->assertSee($deposit->uuid)
            ->assertSee('78.75')
            ->assertSee('3.75')
            ->assertSee('75.00')
            ->assertSee($checkout->id)
            ->assertSee('Ledger Audit Trail');
    }

    public function test_live_pending_and_failed_checkouts_appear_in_real_time_monitor_and_stripe_expiry_updates_status(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_expiry']);

        $pendingCheckout = PaymentCheckout::query()->create([
            'id' => Str::uuid()->toString(),
            'wallet_id' => $this->playerWallet->id,
            'provider' => 'stripe',
            'provider_reference' => 'cs_live_pending_999',
            'currency' => 'USD',
            'amount' => '30.00',
            'fee_amount' => '1.50',
            'total' => '31.50',
            'status' => 'pending',
        ]);

        $failedCheckout = PaymentCheckout::query()->create([
            'id' => Str::uuid()->toString(),
            'wallet_id' => $this->playerWallet->id,
            'provider' => 'payop',
            'provider_reference' => 'inv_live_failed_888',
            'currency' => 'USD',
            'amount' => '20.00',
            'fee_amount' => '1.00',
            'total' => '21.00',
            'status' => 'failed',
            'failed_at' => now(),
        ]);

        Livewire::actingAs($this->admin)
            ->test(DepositAdmin::class)
            ->assertSee('Live Deposit Monitor')
            ->assertSee('1 live checkout(s) currently awaiting payment confirmation.')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['pending_count'] === 1
                && $summary['failed_count'] === 1
                && $summary['live_pending_checkouts_count'] === 1)
            ->call('setViewMode', 'checkouts')
            ->assertSee('cs_live_pending_999')
            ->assertSee('inv_live_failed_888')
            ->call('selectCheckout', $pendingCheckout->id)
            ->assertSee('Checkout Details')
            ->assertSee('Wallet has not been credited for this checkout yet.');

        // Simulate Stripe checkout.session.expired webhook -> flips pending to expired without crediting wallet
        $payload = json_encode([
            'id' => 'evt_expired_999',
            'object' => 'event',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => 'cs_live_pending_999',
                    'object' => 'checkout.session',
                    'metadata' => ['checkout_id' => $pendingCheckout->id],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_expiry');

        $this->call(
            'POST',
            '/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $payload
        )->assertOk()->assertJson(['received' => true, 'handled' => true]);

        $pendingCheckout->refresh();
        $this->assertSame('expired', $pendingCheckout->status);
        $this->assertNotNull($pendingCheckout->failed_at);
        $this->assertNull($pendingCheckout->completed_at);
        $this->assertSame('0.00', $this->playerWallet->fresh()?->cached_balance);
        $this->assertSame(0, Deposit::query()->where('provider_reference', 'cs_live_pending_999')->count());
        $this->assertSame('failed', $failedCheckout->fresh()?->status);
    }
}
