<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\SystemSettingsAdmin;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\Activity;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Operations\Services\SystemSettings;
use App\Modules\Wallet\Actions\RequestWithdrawalAction;
use App\Modules\Wallet\Actions\UpdatePaymentSettingsAction;
use App\Modules\Wallet\Models\PaymentCheckout;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\Withdrawal;
use App\Modules\Wallet\Services\PaymentSettings;
use App\Shared\Support\Currency;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SettingsSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Http::preventStrayRequests();
    }

    public function test_currency_cannot_relabel_a_balance_or_change_after_payment_activity(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $wallet = Wallet::query()->create(['uuid' => Str::uuid(), 'user_id' => $admin->id, 'cached_balance' => '100.00', 'status' => 'active']);
        SystemSetting::query()->updateOrCreate(['key' => 'currency.code'], ['value' => 'EUR']);
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)->set('currencyCode', 'GBP')
            ->call('saveCurrencySettings')->assertHasErrors('currencyCode');
        self::assertSame('EUR', Currency::code());
        self::assertSame('100.00', $wallet->fresh()->cached_balance);
        $wallet->update(['cached_balance' => '0.00']);
        Currency::lockForFinancialActivity();
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)->set('currencyCode', 'GBP')
            ->call('saveCurrencySettings')->assertHasErrors('currencyCode');
    }

    public function test_fee_save_rolls_back_all_values_and_audit_history_on_failure(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        app(SystemSettings::class)->save($admin, ['deposit_fee.enabled' => 'false', 'deposit_fee.fixed' => '1.00', 'deposit_fee.percentage' => '2.00']);
        $before = Activity::count();
        SystemSetting::saving(function (SystemSetting $setting): void {
            if ($setting->key === 'deposit_fee.fixed' && $setting->value === '20.00') {
                throw new RuntimeException('Injected setting failure');
            }
        });
        try {
            Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)->set('depositFeeEnabled', true)
                ->set('depositFeeFixed', '20.00')->call('saveDepositFeeSettings');
            self::fail('Expected the injected failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected setting failure', $exception->getMessage());
        }
        $this->assertDatabaseHas('system_settings', ['key' => 'deposit_fee.enabled', 'value' => 'false']);
        $this->assertDatabaseHas('system_settings', ['key' => 'deposit_fee.fixed', 'value' => '1.00']);
        self::assertSame($before, Activity::count());
    }

    public function test_settings_history_keeps_before_after_and_credentials_are_redacted(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $settings = app(SystemSettings::class);
        $settings->save($admin, ['deposit_fee.fixed' => '1.00']);
        $settings->save($admin, ['deposit_fee.fixed' => '2.00']);
        $history = Activity::query()->where('description', 'system_settings_updated')->latest('id')->firstOrFail();
        self::assertSame('1.00', $history->properties['before']['deposit_fee.fixed']);
        self::assertSame('2.00', $history->properties['after']['deposit_fee.fixed']);
        app(UpdatePaymentSettingsAction::class)->execute($admin, 'payop', false, ['payop.secret' => 'do-not-log-this-secret']);
        self::assertStringNotContainsString('do-not-log-this-secret', Activity::all()->toJson());
        $configuration = app(PaymentSettings::class)->current('payop');
        self::assertStringNotContainsString('do-not-log-this-secret', $configuration->toJson());
        self::assertNotSame('do-not-log-this-secret', $configuration->credentials->firstWhere('name', 'secret')->getRawOriginal('value'));
        $this->assertDatabaseMissing('system_settings', ['key' => 'payments.payop.secret']);
    }

    public function test_credential_reset_explicitly_restores_the_environment_source(): void
    {
        config(['services.payop.secret' => 'environment-secret']);
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $action = app(UpdatePaymentSettingsAction::class);
        $action->execute($admin, 'payop', false, ['payop.secret' => 'saved-secret']);
        $old = app(PaymentSettings::class)->capture('payop');
        $action->execute($admin, 'payop', false, []);
        self::assertSame('saved-secret', app(PaymentSettings::class)->credential('payop.secret'));
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)->set('paymentProvider', 'payop')->set('paymentsEnabled', false)
            ->set('resetPaymentCredentials', ['payop.secret'])->call('savePaymentSettings')->assertHasNoErrors()->assertSet('resetPaymentCredentials', []);
        self::assertSame('environment', app(PaymentSettings::class)->source('payop.secret'));
        self::assertSame('environment-secret', app(PaymentSettings::class)->credential('payop.secret'));
        self::assertSame('saved-secret', $old->fresh()->credential('secret'));
    }

    public function test_admin_can_configure_both_gateways_without_exposing_saved_credentials(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $credentials = [
            'payop.public_key' => 'test-payop-public-key',
            'payop.secret' => 'test-payop-secret',
            'stripe.secret' => 'sk_test_saved-stripe-secret',
            'stripe.webhook_secret' => 'whsec_saved-stripe-secret',
            'payop.payout_token' => 'test-payout-token',
        ];
        $form = Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('paymentProvider', 'payop')->set('paymentsEnabled', true);
        foreach ($credentials as $key => $value) {
            $form->set('paymentCredentials.'.str_replace('.', '_', $key), $value);
        }
        $form->call('savePaymentSettings')->assertHasNoErrors()->assertSet('paymentCredentials', []);
        self::assertSame('payop', app(PaymentSettings::class)->provider());

        $form->set('paymentProvider', 'stripe')->call('savePaymentSettings')->assertHasNoErrors();
        self::assertSame('stripe', app(PaymentSettings::class)->provider());
        $page = $this->actingAs($admin)->get('/admin/system-settings?section=payments')->assertOk();
        foreach ($credentials as $key => $value) {
            [$provider, $name] = explode('.', $key, 2);
            $configuration = app(PaymentSettings::class)->current($provider);
            $stored = $configuration->credentials->firstWhere('name', $name)->getRawOriginal('value');
            self::assertNotSame($value, $stored);
            self::assertSame($value, Crypt::decryptString($stored));
            self::assertSame($value, app(PaymentSettings::class)->credential($key));
            self::assertStringNotContainsString($value, $configuration->toJson());
            self::assertStringNotContainsString($value, Activity::all()->toJson());
            $page->assertDontSee($value, false);
        }
    }

    public function test_view_only_staff_cannot_update_gateway_credentials_through_ui_or_action(): void
    {
        $viewer = User::factory()->withRole('MODERATOR')->create();
        $viewer->givePermissionTo('system_settings.view');
        $version = app(PaymentSettings::class)->current('stripe')->id;
        Livewire::actingAs($viewer)->test(SystemSettingsAdmin::class)
            ->set('paymentCredentials.stripe_secret', 'unauthorized-secret')
            ->call('savePaymentSettings')->assertForbidden();
        self::assertSame($version, app(PaymentSettings::class)->current('stripe')->id);

        $this->expectException(AuthorizationException::class);
        app(UpdatePaymentSettingsAction::class)->execute($viewer, 'stripe', false, ['stripe.secret' => 'unauthorized-secret']);
    }

    public function test_unsupported_gateway_is_rejected_without_saving_settings(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $version = app(PaymentSettings::class)->current('stripe')->id;
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('paymentProvider', 'unsupported')->call('savePaymentSettings')->assertHasErrors('paymentProvider');
        self::assertSame($version, app(PaymentSettings::class)->current('stripe')->id);
        $this->assertDatabaseMissing('system_settings', ['key' => 'payments.provider', 'value' => 'unsupported']);
    }

    public function test_payop_validation_does_not_hold_a_write_transaction_and_failed_validation_saves_nothing(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $certificate = base64_encode(sodium_crypto_box_publickey(sodium_crypto_box_keypair()));
        $level = DB::transactionLevel();
        Http::fake(function () use ($level) {
            self::assertSame($level, DB::transactionLevel());

            return Http::response(['status' => 1, 'data' => [['type' => 20, 'currencies' => [Currency::code()]]]]);
        });
        $action = app(UpdatePaymentSettingsAction::class);
        $action->execute($admin, 'payop', false, ['payop.payout_token' => 'saved-token', 'payop.payout_certificate' => $certificate], 'payop');
        $version = app(PaymentSettings::class)->current('payop')->id;
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([], 503)]);
        try {
            $action->execute($admin, 'payop', false, ['payop.payout_token' => 'bad-token'], 'payop');
            self::fail('Expected verification failure.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('payoutProvider', $exception->errors());
        }
        self::assertSame($version, app(PaymentSettings::class)->current('payop')->id);
        self::assertSame('saved-token', app(PaymentSettings::class)->credential('payop.payout_token'));
    }

    public function test_normalized_credential_migration_preserves_encryption_and_pending_links(): void
    {
        $migration = require database_path('migrations/2026_11_12_010000_separate_payment_gateway_credentials.php');
        $migration->down();
        $encrypted = Crypt::encryptString('legacy-secret');
        SystemSetting::query()->create(['key' => 'payments.payop.secret', 'value' => $encrypted]);
        SystemSetting::query()->create(['key' => 'payments.stripe.webhook_secret', 'value' => null]);
        config(['services.stripe.webhook_secret' => 'environment-verifier']);
        $migration->up();
        app(PaymentSettings::class)->forget();
        self::assertSame('environment', app(PaymentSettings::class)->source('stripe.webhook_secret'));
        self::assertSame('environment-verifier', app(PaymentSettings::class)->credential('stripe.webhook_secret'));
        self::assertSame('legacy-secret', app(PaymentSettings::class)->credential('payop.secret'));
        self::assertSame($encrypted, app(PaymentSettings::class)->current('payop')->credentials->firstWhere('name', 'secret')->getRawOriginal('value'));
        $this->assertDatabaseMissing('system_settings', ['key' => 'payments.payop.secret']);
        $this->assertDatabaseMissing('system_settings', ['key' => 'feature.kyc_required']);
    }

    public function test_new_stripe_checkout_can_use_its_original_signing_secret_after_a_switch(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $wallet = Wallet::query()->create(['uuid' => Str::uuid(), 'user_id' => $admin->id, 'cached_balance' => '0.00', 'status' => 'active']);
        $action = app(UpdatePaymentSettingsAction::class);
        $action->execute($admin, 'stripe', false, ['stripe.webhook_secret' => 'old-signing-secret']);
        $configuration = app(PaymentSettings::class)->capture('stripe');
        $checkout = PaymentCheckout::query()->create(['id' => Str::uuid(), 'wallet_id' => $wallet->id, 'provider' => 'stripe', 'provider_reference' => 'cs_old_account', 'gateway_configuration_id' => $configuration->id, 'currency' => Currency::code(), 'amount' => '25.00', 'fee_amount' => '0.00', 'total' => '25.00']);
        $action->execute($admin, 'stripe', false, ['stripe.webhook_secret' => 'new-signing-secret']);
        $migration = require database_path('migrations/2026_11_12_010000_separate_payment_gateway_credentials.php');
        try {
            $migration->down();
            self::fail('Rollback must preserve credentials still needed by a pending checkout.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('Reconcile payments', $exception->getMessage());
        }
        $payload = json_encode(['id' => 'evt_old_account', 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_old_account', 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => 2500, 'currency' => strtolower(Currency::code()), 'metadata' => ['wallet_id' => (string) $wallet->id, 'wallet_credit_amount' => '25.00', 'processing_fee_amount' => '0.00']]]]);
        $time = time();
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$payload, 'old-signing-secret')];
        $this->call('POST', '/stripe/webhook', [], [], [], $headers, $payload)->assertOk();
        $this->call('POST', '/stripe/webhook', [], [], [], $headers, $payload)->assertOk();
        self::assertSame('25.00', $wallet->fresh()->cached_balance);
        self::assertNotNull($checkout->fresh()->completed_at);
    }

    public function test_withdrawal_limits_include_other_pending_requests_and_reset_at_utc_midnight(): void
    {
        Event::fake();
        $user = User::factory()->create();
        Wallet::query()->create(['uuid' => Str::uuid(), 'user_id' => $user->id, 'cached_balance' => '2000.00', 'status' => 'active']);
        KycSubmission::query()->create(['uuid' => Str::uuid(), 'user_id' => $user->id, 'status' => 'approved']);
        SystemSetting::query()->updateOrCreate(['key' => 'wallet.withdrawal_limit_min'], ['value' => '10.00']);
        SystemSetting::query()->updateOrCreate(['key' => 'wallet.withdrawal_limit_max_daily'], ['value' => '1000.00']);
        $this->travelTo(now('UTC')->startOfDay()->addHours(23));
        $action = app(RequestWithdrawalAction::class);
        foreach (['1.00', '1500.00'] as $amount) {
            try {
                $action->execute($user, $amount);
                self::fail('Expected a limit failure.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('amount', $exception->errors());
            }
        }
        $first = $action->execute($user, '700.00');
        try {
            $action->execute($user, '301.00');
            self::fail('Expected the cumulative daily limit.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('amount', $exception->errors());
        }
        $first->update(['status' => 'rejected']);
        $action->execute($user, '1000.00');
        $this->travelTo(now('UTC')->addDay()->startOfDay());
        $action->execute($user, '1000.00');
        self::assertSame(3, Withdrawal::count());
    }

    public function test_settings_snapshot_loads_once_and_invalidates_after_a_save(): void
    {
        $admin = User::factory()->withRole('SUPER_ADMIN')->create();
        $settings = app(SystemSettings::class);
        $settings->forget();
        DB::enableQueryLog();
        $settings->get('deposit_fee.fixed');
        $settings->get('referral.enabled');
        $queries = array_filter(DB::getQueryLog(), fn ($entry) => str_contains($entry['query'], 'system_settings'));
        self::assertCount(1, $queries);
        $settings->save($admin, ['deposit_fee.fixed' => '3.00']);
        self::assertSame('3.00', $settings->get('deposit_fee.fixed'));
    }
}
