<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Livewire\Admin\SystemSettingsAdmin;
use App\Livewire\Wallet\WalletDashboard;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Actions\RequestWithdrawalAction;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WithdrawalFeeCalculator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class WithdrawalFeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_disable_deposit_and_withdrawal_fees_independently_and_pending_requests_keep_their_fee(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $form = Livewire::actingAs($admin)->test(SystemSettingsAdmin::class);
        $form->set('depositFeeEnabled', true)->set('depositFeePercentage', '10.00')->call('saveDepositFeeSettings')->assertHasNoErrors();
        $form->set('withdrawalFeeEnabled', true)->set('withdrawalFeePercentage', '10.00')->call('saveWithdrawalFeeSettings')->assertHasNoErrors();
        $user = User::factory()->withRole('PLAYER')->create();
        Wallet::query()->create(['uuid' => Str::uuid(), 'user_id' => $user->id, 'cached_balance' => '500.00', 'status' => 'active']);
        KycSubmission::query()->create(['uuid' => Str::uuid(), 'user_id' => $user->id, 'status' => 'approved']);
        $first = app(RequestWithdrawalAction::class)->execute($user, '100.00');
        self::assertSame('100.00', $first->amount);
        self::assertSame('10.00', $first->fee_amount);
        self::assertSame('90.00', $first->payout_amount);
        Livewire::actingAs($user)->test(WalletDashboard::class)->set('amount', '100')
            ->assertViewHas('withdrawalBreakdown', ['payout' => '90.00', 'fee' => '10.00', 'total' => '100.00']);

        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('withdrawalFeeEnabled', false)->call('saveWithdrawalFeeSettings')->assertHasNoErrors();
        $second = app(RequestWithdrawalAction::class)->execute($user, '100.00');
        self::assertSame('0.00', $second->fee_amount);
        self::assertSame('100.00', $second->payout_amount);
        self::assertSame('90.00', $first->fresh()->payout_amount);
        $this->assertDatabaseHas('system_settings', ['key' => 'deposit_fee.enabled', 'value' => 'true']);

        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('depositFeeEnabled', false)->call('saveDepositFeeSettings')->assertHasNoErrors();
        $this->assertDatabaseHas('system_settings', ['key' => 'deposit_fee.enabled', 'value' => 'false']);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/wallet/withdraw', ['amount' => '100.00', 'fee_amount' => '99.00'])
            ->assertCreated()->assertJsonPath('data.amount', '100.00')->assertJsonPath('data.fee_amount', '0.00')->assertJsonPath('data.payout_amount', '100.00');
    }

    public function test_withdrawal_fee_defaults_to_disabled(): void
    {
        self::assertSame(['payout' => '100.00', 'fee' => '0.00', 'total' => '100.00'], app(WithdrawalFeeCalculator::class)->calculate(100));
    }
}
