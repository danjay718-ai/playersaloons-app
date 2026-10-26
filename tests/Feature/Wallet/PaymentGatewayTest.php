<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Livewire\Admin\SystemSettingsAdmin;
use App\Livewire\Wallet\WalletDashboard;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\SystemSetting;
use App\Modules\Wallet\Actions\UpdatePaymentSettingsAction;
use App\Modules\Wallet\Models\PaymentCheckout;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\PaymentSettings;
use App\Modules\Wallet\Services\StripeCheckoutService;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Http::preventStrayRequests();
        config(['services.payop.public_key' => 'application-example', 'services.payop.secret' => 'payop-secret', 'app.currency' => 'USD']);
    }

    public function test_admin_can_switch_gateways_and_store_credentials_without_exposing_saved_values(): void
    {
        $admin = $this->user('SUPER_ADMIN');
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('paymentProvider', 'payop')
            ->set('paymentCredentials.payop_public_key', 'application-new')
            ->set('paymentCredentials.payop_secret', 'new-secret-value')
            ->call('savePaymentSettings')->assertHasNoErrors()
            ->assertSet('paymentCredentials', [])
            ->assertSee('Payment settings updated.')
            ->assertDontSee('new-secret-value');

        $stored = SystemSetting::query()->where('key', 'payments.payop.secret')->value('value');
        self::assertNotSame('new-secret-value', $stored);
        self::assertSame('new-secret-value', Crypt::decryptString($stored));
        self::assertSame('payop', app(PaymentSettings::class)->provider());
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->assertSet('paymentCredentials', [])->assertDontSee('new-secret-value')
            ->call('savePaymentSettings')->assertHasNoErrors();
        self::assertSame('new-secret-value', app(PaymentSettings::class)->credential('payop.secret'));
        $this->assertDatabaseHas('system_settings', ['key' => 'payments.payop.secret', 'updated_by' => $admin->id]);
    }

    public function test_gateway_settings_reject_unknown_gateways_missing_keys_and_unauthorized_changes(): void
    {
        config(['services.payop.public_key' => '', 'services.payop.secret' => '']);
        $admin = $this->user('SUPER_ADMIN');
        Livewire::actingAs($admin)->test(SystemSettingsAdmin::class)
            ->set('paymentProvider', 'other')->call('savePaymentSettings')->assertHasErrors('paymentProvider')
            ->set('paymentProvider', 'payop')->call('savePaymentSettings')->assertHasErrors('paymentProvider');
        self::assertSame('stripe', app(PaymentSettings::class)->provider());

        $viewer = $this->user('MODERATOR');
        $viewer->givePermissionTo('system_settings.view');
        Livewire::actingAs($viewer)->test(SystemSettingsAdmin::class)
            ->set('paymentProvider', 'payop')->call('savePaymentSettings')->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(UpdatePaymentSettingsAction::class)->execute($viewer, 'payop', false, []);
    }

    public function test_payop_checkout_uses_saved_totals_and_a_signed_invoice_then_redirects(): void
    {
        $user = $this->user();
        $wallet = $this->wallet($user);
        $invoice = Str::uuid()->toString();
        Http::fake(['api.payop.com/v1/invoices/create' => Http::response(['status' => 1, 'data' => 'ignored'], 200, ['identifier' => $invoice])]);
        $this->setting('payments.provider', 'payop');
        $this->setting('deposit_fee.enabled', 'true');
        $this->setting('deposit_fee.fixed', '2.00');

        Livewire::actingAs($user)->test(WalletDashboard::class)
            ->assertSee('PayOp')->assertDontSee('Stripe Sandbox')->assertDontSee('4242 4242')
            ->set('depositAmount', '50')->call('deposit')
            ->assertRedirect('https://checkout.payop.com/en/payment/invoice-preprocessing/'.$invoice);
        $checkout = PaymentCheckout::query()->sole();
        self::assertSame('50.00', $checkout->amount);
        self::assertSame('52.00', $checkout->total);
        self::assertSame($wallet->id, $checkout->wallet_id);
        Http::assertSent(fn (Request $request): bool => $request['order']['id'] === $checkout->id
            && $request['order']['amount'] === '52.00'
            && $request['signature'] === hash('sha256', '52.00:USD:'.$checkout->id.':payop-secret')
            && $request['payer']['email'] === $user->email);
        self::assertSame('25.00', $wallet->fresh()->cached_balance);
    }

    public function test_disabled_deposits_do_not_call_any_gateway(): void
    {
        $user = $this->user();
        $wallet = $this->wallet($user);
        $this->setting('payments.enabled', 'false');
        $this->mock(StripeCheckoutService::class)->shouldNotReceive('createDepositSession');
        Livewire::actingAs($user)->test(WalletDashboard::class)->set('depositAmount', '50')->call('deposit')
            ->assertSee('Deposits are temporarily unavailable. Please try again later.');
        Http::assertNothingSent();
        self::assertSame('25.00', $wallet->fresh()->cached_balance);
    }

    public function test_deposit_rejects_fractional_cents_before_creating_checkout(): void
    {
        $user = $this->user();
        $this->wallet($user);
        Livewire::actingAs($user)->test(WalletDashboard::class)->set('depositAmount', '50.001')->call('deposit')->assertHasErrors('depositAmount');
        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_checkouts', 0);
    }

    public function test_verified_payment_is_credited_once_after_gateway_and_fee_settings_change(): void
    {
        $checkout = $this->checkout();
        $this->setting('payments.provider', 'stripe');
        $this->setting('payments.enabled', 'false');
        $this->setting('deposit_fee.fixed', '99.00');
        Http::fake(['api.payop.com/*' => Http::response($this->invoice($checkout))]);
        $payload = ['invoice' => ['id' => $checkout->provider_reference]];
        $this->postJson('/payop/webhook', $payload)->assertOk()->assertJson(['handled' => true]);
        $this->postJson('/payop/webhook', $payload)->assertOk();
        $this->assertDatabaseCount('deposits', 1);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseHas('deposits', ['provider' => 'payop', 'amount' => '50.00', 'fee_amount' => '2.00']);
        self::assertSame('75.00', Wallet::query()->findOrFail($checkout->wallet_id)->cached_balance);
        self::assertNotNull($checkout->fresh()->completed_at);
        Http::assertSentCount(1);
    }

    public function test_untrusted_success_payload_cannot_credit_an_unpaid_invoice(): void
    {
        $checkout = $this->checkout();
        $invoice = $this->invoice($checkout);
        $invoice['data']['status'] = 4;
        Http::fake(['api.payop.com/*' => Http::response($invoice)]);
        $this->postJson('/payop/webhook', ['invoice' => ['id' => $checkout->provider_reference, 'status' => 1], 'transaction' => ['state' => 2]])
            ->assertOk()->assertJson(['handled' => false]);
        $this->assertDatabaseCount('deposits', 0);
        self::assertNull($checkout->fresh()->completed_at);
    }

    #[DataProvider('mismatches')]
    public function test_mismatched_verified_invoice_is_rejected(string $field, mixed $value): void
    {
        $checkout = $this->checkout();
        $invoice = $this->invoice($checkout);
        $invoice['data'][$field] = $value;
        Http::fake(['api.payop.com/*' => Http::response($invoice)]);
        $this->postJson('/payop/webhook', ['invoice' => ['id' => $checkout->provider_reference]])->assertUnprocessable();
        $this->assertDatabaseCount('deposits', 0);
        self::assertSame('25.00', Wallet::query()->findOrFail($checkout->wallet_id)->cached_balance);
    }

    public static function mismatches(): array
    {
        return ['amount' => ['amount', '51.99'], 'currency' => ['currency', 'EUR'], 'order' => ['orderIdentifier', 'other'], 'invoice' => ['identifier', 'other']];
    }

    public function test_verification_outage_returns_retryable_error_and_unknown_invoices_are_ignored(): void
    {
        $checkout = $this->checkout();
        Http::fake(['api.payop.com/*' => Http::response([], 503)]);
        $this->postJson('/payop/webhook', ['invoice' => ['id' => $checkout->provider_reference]])->assertStatus(503);
        $this->postJson('/payop/webhook', ['invoice' => ['id' => Str::uuid()->toString()]])->assertOk();
        $this->postJson('/payop/webhook', ['invoice' => ['id' => '../anything']])->assertUnprocessable();
        $this->assertDatabaseCount('deposits', 0);
        Http::assertSentCount(1);
    }

    public function test_timeout_after_remote_invoice_creation_can_be_recovered_by_verified_order(): void
    {
        $checkout = $this->checkout();
        $invoice = $this->invoice($checkout);
        $invoiceId = $checkout->provider_reference;
        $checkout->update(['provider_reference' => null]);
        Http::fake(['api.payop.com/*' => Http::response($invoice)]);
        $this->postJson('/payop/webhook', ['invoice' => ['id' => $invoiceId], 'transaction' => ['order' => ['id' => $checkout->id]]])->assertOk();
        self::assertSame($invoiceId, $checkout->fresh()->provider_reference);
        $this->assertDatabaseCount('deposits', 1);
    }

    public function test_invoice_creation_failure_does_not_credit_wallet_or_redirect(): void
    {
        $user = $this->user();
        $wallet = $this->wallet($user);
        $this->setting('payments.provider', 'payop');
        Http::fake(['api.payop.com/*' => Http::response(['message' => 'Wrong signature'], 400)]);
        Livewire::actingAs($user)->test(WalletDashboard::class)->set('depositAmount', '50')->call('deposit')
            ->assertSee('Deposits are temporarily unavailable. Please try again later.')->assertNoRedirect();
        $this->assertDatabaseCount('deposits', 0);
        self::assertSame('25.00', $wallet->fresh()->cached_balance);
    }

    private function user(string $role = 'PLAYER'): User
    {
        $id = Str::uuid()->toString();
        $user = User::query()->create(['uuid' => $id, 'username' => 'payment-'.substr($id, 0, 8), 'email' => $id.'@example.com', 'password' => bcrypt('password'), 'status' => UserStatus::ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    private function wallet(User $user): Wallet
    {
        return Wallet::query()->create(['uuid' => Str::uuid()->toString(), 'user_id' => $user->id, 'cached_balance' => '25.00', 'status' => WalletStatus::ACTIVE]);
    }

    private function checkout(): PaymentCheckout
    {
        $wallet = $this->wallet($this->user());

        return PaymentCheckout::query()->create(['id' => Str::uuid()->toString(), 'wallet_id' => $wallet->id, 'provider' => 'payop', 'provider_reference' => Str::uuid()->toString(), 'currency' => 'USD', 'amount' => '50.00', 'fee_amount' => '2.00', 'total' => '52.00']);
    }

    private function invoice(PaymentCheckout $checkout): array
    {
        return ['status' => 1, 'data' => ['identifier' => $checkout->provider_reference, 'orderIdentifier' => $checkout->id, 'amount' => $checkout->total, 'currency' => $checkout->currency, 'status' => 1]];
    }

    private function setting(string $key, string $value): void
    {
        SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
