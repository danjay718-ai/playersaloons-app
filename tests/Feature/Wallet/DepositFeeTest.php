<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Livewire\Admin\SystemSettingsAdmin;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Wallet\Services\DepositFeeCalculator;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
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

        $this->assertSame(['credit' => '50.00', 'fee' => '2.00', 'total' => '52.00'], app(DepositFeeCalculator::class)->calculate(50));
    }

    public function test_disabled_fee_returns_requested_credit_as_total(): void
    {
        $this->assertSame(['credit' => '50.00', 'fee' => '0.00', 'total' => '50.00'], app(DepositFeeCalculator::class)->calculate(50));
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
}
