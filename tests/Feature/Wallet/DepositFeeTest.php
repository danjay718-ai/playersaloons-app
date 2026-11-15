<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Livewire\Admin\SystemSettingsAdmin;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\DepositFeeCalculator;
use App\Modules\Wallet\Services\PayopCheckoutService;
use App\Modules\Wallet\Services\StripeCheckoutService;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DepositFeeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    }

    public function test_fee_calculator_combines_fixed_and_percentage_fees(): void
    {
        SystemSetting::query()->where('key', 'deposit_fee.enabled')->update(['value' => 'true']);
        SystemSetting::query()->where('key', 'deposit_fee.fixed')->update(['value' => '1.00']);
        SystemSetting::query()->where('key', 'deposit_fee.percentage')->update(['value' => '2.00']);

        $this->assertSame(['credit' => '48.00', 'fee' => '2.00', 'total' => '50.00'], app(DepositFeeCalculator::class)->calculate(50));
    }

    public function test_disabled_fee_returns_requested_credit_as_total(): void
    {
        $this->assertSame(['credit' => '50.00', 'fee' => '0.00', 'total' => '50.00'], app(DepositFeeCalculator::class)->calculate(50));
    }

    public function test_percentage_fee_rounds_to_cents_without_changing_the_payment_total(): void
    {
        SystemSetting::query()->where('key', 'deposit_fee.enabled')->update(['value' => 'true']);
        SystemSetting::query()->where('key', 'deposit_fee.percentage')->update(['value' => '10.00']);

        self::assertSame(['credit' => '45.04', 'fee' => '5.01', 'total' => '50.05'], app(DepositFeeCalculator::class)->calculate(50.05));
        self::assertSame(['credit' => '0.00', 'fee' => '0.00', 'total' => '0.00'], app(DepositFeeCalculator::class)->calculate(0));
    }

    #[DataProvider('feesWithoutWalletCredit')]
    public function test_checkout_rejects_fees_that_leave_no_wallet_credit(string $service, string $fixed, string $percentage): void
    {
        config(['services.stripe.secret' => 'sk_test_deposit', 'services.payop.public_key' => 'test-key', 'services.payop.secret' => 'test-secret']);
        foreach (['enabled' => 'true', 'fixed' => $fixed, 'percentage' => $percentage] as $key => $value) {
            SystemSetting::query()->where('key', 'deposit_fee.'.$key)->update(['value' => $value]);
        }
        $user = User::factory()->create();
        $wallet = Wallet::query()->create(['uuid' => Str::uuid(), 'user_id' => $user->id, 'cached_balance' => '0.00', 'status' => 'active']);
        try {
            app($service)->createDepositSession($user, $wallet, 100);
            self::fail('A checkout must leave a positive wallet credit.');
        } catch (LogicException $exception) {
            self::assertSame('Deposit amount must exceed the processing fee.', $exception->getMessage());
        }
        $this->assertDatabaseCount('payment_checkouts', 0);
    }

    public static function feesWithoutWalletCredit(): array
    {
        return [
            'Payop equal fee' => [PayopCheckoutService::class, '100.00', '0.00'],
            'Payop excess fee' => [PayopCheckoutService::class, '150.00', '0.00'],
            'Payop full percentage' => [PayopCheckoutService::class, '0.00', '100.00'],
            'Stripe equal fee' => [StripeCheckoutService::class, '100.00', '0.00'],
            'Stripe excess fee' => [StripeCheckoutService::class, '150.00', '0.00'],
            'Stripe full percentage' => [StripeCheckoutService::class, '0.00', '100.00'],
        ];
    }

    public function test_admin_can_update_deposit_fee_settings(): void
    {
        $admin = User::query()->create(['uuid' => Str::uuid(), 'email' => 'fee-admin@example.com', 'username' => 'fee-admin', 'password' => 'password', 'status' => UserStatus::ACTIVE, 'email_verified_at' => now()]);
        $admin->assignRole('ADMIN');

        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('depositFeeEnabled', true)->set('depositFeeFixed', '1.25')->set('depositFeePercentage', '2.50')
            ->call('saveDepositFeeSettings')->assertHasNoErrors()->assertSee('Deposit fee settings updated.');

        $this->assertDatabaseHas('system_settings', ['key' => 'deposit_fee.fixed', 'value' => '1.25', 'updated_by' => $admin->id]);
        $this->assertDatabaseHas('system_settings', ['key' => 'deposit_fee.percentage', 'value' => '2.50', 'updated_by' => $admin->id]);
    }

    public function test_admin_can_enable_ten_percent_deposit_fee_separately_from_tournament_commission(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();

        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('depositFeeEnabled', true)->set('depositFeeFixed', '0.00')->set('depositFeePercentage', '10.00')
            ->call('saveDepositFeeSettings')->assertHasNoErrors();

        Livewire::test(SystemSettingsAdmin::class)
            ->assertSet('depositFeeEnabled', true)->assertSet('depositFeeFixed', '0.00')
            ->assertSet('depositFeePercentage', '10.00')->assertSet('platformCommissionPercentage', '10.00');
        self::assertSame(['credit' => '90.00', 'fee' => '10.00', 'total' => '100.00'], app(DepositFeeCalculator::class)->calculate(100));
        $this->assertDatabaseHas('system_settings', ['key' => 'platform.commission_percentage', 'value' => '10.00']);
    }

    public function test_admin_can_update_default_tournament_result_timeout(): void
    {
        $admin = User::query()->create(['uuid' => Str::uuid(), 'email' => 'timing-admin@example.com', 'username' => 'timing-admin', 'password' => 'password', 'status' => UserStatus::ACTIVE, 'email_verified_at' => now()]);
        $admin->assignRole('ADMIN');

        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('defaultWaitingResultTime', 45)
            ->call('saveTournamentSettings')->assertHasNoErrors();

        $this->assertDatabaseHas('system_settings', ['key' => 'tournament.waiting_result_time_default', 'value' => '45', 'updated_by' => $admin->id]);
    }

    public function test_admin_can_update_platform_commission_for_all_winner_payouts(): void
    {
        $admin = User::query()->create(['uuid' => Str::uuid(), 'email' => 'commission-admin@example.com', 'username' => 'commission-admin', 'password' => 'password', 'status' => UserStatus::ACTIVE, 'email_verified_at' => now()]);
        $admin->assignRole('ADMIN');

        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('platformCommissionPercentage', '12.50')
            ->call('savePlatformCommissionSettings')->assertHasNoErrors()->assertSee('Platform commission settings updated.');

        $this->assertDatabaseHas('system_settings', ['key' => 'platform.commission_percentage', 'value' => '12.50', 'updated_by' => $admin->id]);
    }

    public function test_only_super_admin_can_open_the_local_tournament_test_reset(): void
    {
        $admin = User::query()->create(['uuid' => Str::uuid(), 'email' => 'reset-admin@example.com', 'username' => 'reset-admin', 'password' => 'password', 'status' => UserStatus::ACTIVE, 'email_verified_at' => now()]);
        $admin->assignRole('ADMIN');

        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->call('openTournamentTestingReset')
            ->assertForbidden();
    }
}
