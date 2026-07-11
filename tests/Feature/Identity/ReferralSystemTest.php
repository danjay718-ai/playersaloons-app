<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Livewire\Admin\SystemSettingsAdmin;
use App\Modules\Identity\Actions\RegisterUserAction;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Models\Referral;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Wallet\Listeners\CreateWalletListener;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ReferralSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    }

    private function user(string $email, string $role = 'PLAYER'): User
    {
        $user = User::query()->create(['uuid' => Str::uuid(), 'email' => $email, 'username' => Str::before($email, '@'), 'password' => 'password', 'status' => UserStatus::ACTIVE, 'email_verified_at' => now()]);
        $user->assignRole($role);
        (new CreateWalletListener)->handle(new UserRegistered($user->id, $user->email, $user->username));

        return $user;
    }

    public function test_registration_records_valid_referrer(): void
    {
        $referrer = $this->user('referrer@example.com');

        $referred = app(RegisterUserAction::class)->execute([
            'email' => 'new@example.com', 'username' => 'newplayer', 'password' => 'password', 'referrer_id' => $referrer->id,
        ]);

        $this->assertDatabaseHas('referrals', ['referrer_id' => $referrer->id, 'referred_user_id' => $referred->id, 'status' => 'pending']);
    }

    public function test_dynamic_rewards_are_credited_once_after_verification(): void
    {
        $referrer = $this->user('dynamic-referrer@example.com');
        $referred = $this->user('dynamic-new@example.com');
        Referral::query()->create(['uuid' => Str::uuid(), 'referrer_id' => $referrer->id, 'referred_user_id' => $referred->id, 'status' => 'pending']);
        SystemSetting::query()->where('key', 'referral.referrer_reward')->update(['value' => '8.75']);
        SystemSetting::query()->where('key', 'referral.referred_reward')->update(['value' => '3.25']);

        event(new Verified($referred));
        event(new Verified($referred));

        $this->assertSame('8.75', $referrer->wallet()->firstOrFail()->cached_balance);
        $this->assertSame('3.25', $referred->wallet()->firstOrFail()->cached_balance);
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertDatabaseHas('referrals', ['referred_user_id' => $referred->id, 'status' => 'rewarded', 'referrer_reward' => 8.75, 'referred_reward' => 3.25]);
    }

    public function test_disabled_referrals_remain_pending_without_wallet_credits(): void
    {
        $referrer = $this->user('disabled-referrer@example.com');
        $referred = $this->user('disabled-new@example.com');
        Referral::query()->create(['uuid' => Str::uuid(), 'referrer_id' => $referrer->id, 'referred_user_id' => $referred->id, 'status' => 'pending']);
        SystemSetting::query()->where('key', 'referral.enabled')->update(['value' => 'false']);

        event(new Verified($referred));

        $this->assertDatabaseHas('referrals', ['referred_user_id' => $referred->id, 'status' => 'pending']);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_admin_can_update_dynamic_referral_settings(): void
    {
        $admin = $this->user('settings-admin@example.com', 'ADMIN');

        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('referralEnabled', true)->set('referrerReward', '12.50')->set('referredReward', '4.75')
            ->call('saveReferralSettings')->assertHasNoErrors()->assertSee('Referral settings updated.');

        $this->assertDatabaseHas('system_settings', ['key' => 'referral.referrer_reward', 'value' => '12.50', 'updated_by' => $admin->id]);
        $this->assertDatabaseHas('system_settings', ['key' => 'referral.referred_reward', 'value' => '4.75', 'updated_by' => $admin->id]);
    }

    public function test_support_staff_cannot_manage_referral_settings(): void
    {
        $support = $this->user('referral-support@example.com', 'SUPPORT_AGENT');
        $this->actingAs($support)->get('/admin/system-settings')->assertForbidden();
    }
}
