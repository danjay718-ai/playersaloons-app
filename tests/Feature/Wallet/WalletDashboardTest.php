<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Livewire\Wallet\WalletDashboard;
use App\Modules\Identity\Models\KycSubmission;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\ErrorIncident;
use App\Modules\Wallet\Actions\RequestWithdrawalAction;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\StripeCheckoutService;
use App\Shared\Enums\KycStatus;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WalletDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_deposit_redirects_to_stripe_checkout(): void
    {
        $user = $this->createPlayerWithWallet('25.00');

        $this->mock(StripeCheckoutService::class)
            ->shouldReceive('createDepositSession')
            ->once()
            ->withArgs(fn (User $checkoutUser, Wallet $wallet, float $amount): bool => $checkoutUser->is($user)
                && (int) $wallet->getAttribute('user_id') === (int) $user->getKey()
                && $amount === 50.0)
            ->andReturn('https://checkout.stripe.test/session');

        Livewire::actingAs($user)
            ->test(WalletDashboard::class)
            ->assertSee('$25.00')
            ->set('depositAmount', '50')
            ->call('deposit')
            ->assertRedirect('https://checkout.stripe.test/session');

        $this->assertSame('25.00', $user->wallet()->first()?->cached_balance);
    }

    public function test_unconfigured_deposits_show_a_friendly_notice_and_keep_balance_unchanged(): void
    {
        config()->set('services.stripe.secret', '');
        $user = $this->createPlayerWithWallet('25.00');

        Livewire::actingAs($user)
            ->test(WalletDashboard::class)
            ->set('depositAmount', '50')
            ->call('deposit')
            ->assertSee('Deposits are temporarily unavailable. Please try again later.')
            ->assertDontSee('Stripe secret key is not configured.')
            ->assertDontSee('Reference: ERR-');

        $this->assertSame('25.00', $user->wallet()->first()?->cached_balance);
        $this->assertDatabaseHas('error_incidents', [
            'message' => 'Stripe secret key is not configured.',
        ]);
    }

    #[DataProvider('unapprovedKycStatuses')]
    public function test_withdrawal_without_approved_kyc_shows_a_clear_validation_message_without_an_incident_reference(?KycStatus $status): void
    {
        $user = $this->createPlayerWithWallet('100.00');
        $user->forceFill(['email_verified_at' => now()])->save();
        if ($status !== null) {
            $this->createKyc($user, $status);
        }

        Livewire::actingAs($user)->test(WalletDashboard::class)
            ->assertViewHas('withdrawalKycApproved', false)
            ->assertSee('Check identity verification')
            ->set('amount', '50.00')->call('withdraw')
            ->assertHasErrors('amount')
            ->assertSee('Your identity verification must be approved before you can withdraw. Check your verification status in Profile.')
            ->assertDontSee('Unable to create the withdrawal request.')
            ->assertDontSee('Reference: ERR-');

        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseCount('error_incidents', 0);
        $this->assertSame('100.00', $user->wallet()->firstOrFail()->cached_balance);
    }

    public static function unapprovedKycStatuses(): array
    {
        return [
            'missing' => [null],
            'not submitted' => [KycStatus::NOT_SUBMITTED],
            'submitted' => [KycStatus::SUBMITTED],
            'under review' => [KycStatus::UNDER_REVIEW],
            'rejected' => [KycStatus::REJECTED],
        ];
    }

    public static function withdrawalCurrencies(): array
    {
        return ['Euro' => ['EUR', '€'], 'Pound' => ['GBP', '£']];
    }

    #[DataProvider('withdrawalCurrencies')]
    public function test_verified_players_with_approved_kyc_can_submit_withdrawals_without_changing_balances(string $currency, string $symbol): void
    {
        config()->set('app.currency', $currency);
        $user = $this->createPlayerWithWallet('100.00');
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->createKyc($user, KycStatus::APPROVED);
        Livewire::actingAs($user)->test(WalletDashboard::class)
            ->assertViewHas('withdrawalKycApproved', true)
            ->set('amount', '50.00')->call('withdraw')
            ->assertHasNoErrors()->assertSet('amount', '')
            ->assertSee('Withdrawal request of '.$symbol.'50.00 submitted successfully and is pending review!')
            ->assertDontSee('Reference: ERR-');
        $this->assertDatabaseHas('withdrawals', ['user_id' => $user->id, 'status' => 'pending', 'amount' => '50.00']);
        $this->assertSame('100.00', $user->wallet()->firstOrFail()->cached_balance);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_insufficient_balance_has_a_clear_message_without_exposing_wallet_details(): void
    {
        $user = $this->createPlayerWithWallet('25.00');
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->createKyc($user, KycStatus::APPROVED);
        Livewire::actingAs($user)->test(WalletDashboard::class)
            ->set('amount', '50.00')->call('withdraw')->assertHasErrors('amount')
            ->assertSee('Your available balance is not enough for this withdrawal.')
            ->assertDontSee('Wallet UUID:')->assertDontSee('Reference: ERR-');
        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseCount('error_incidents', 0);
        $this->assertSame('25.00', $user->wallet()->firstOrFail()->cached_balance);
    }

    public function test_unverified_email_is_explained_without_creating_an_error_incident(): void
    {
        $user = $this->createPlayerWithWallet('100.00');
        Livewire::actingAs($user)->test(WalletDashboard::class)
            ->set('amount', '50.00')->call('withdraw')->assertHasErrors('amount')
            ->assertSee('Email verification is required before you can request a withdrawal.')
            ->assertDontSee('Reference: ERR-');
        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseCount('error_incidents', 0);
    }

    public function test_withdrawal_api_returns_a_clear_json_422_for_unapproved_kyc(): void
    {
        $user = $this->createPlayerWithWallet('100.00');
        $user->forceFill(['email_verified_at' => now()])->save();
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.wallet.withdraw'), ['amount' => 50])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Your identity verification must be approved before you can withdraw. Check your verification status in Profile.']);

        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseCount('error_incidents', 0);
        $this->assertSame('100.00', $user->wallet()->firstOrFail()->cached_balance);
    }

    public function test_withdrawal_api_returns_a_safe_json_422_for_insufficient_balance(): void
    {
        $user = $this->createPlayerWithWallet('25.00');
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->createKyc($user, KycStatus::APPROVED);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.wallet.withdraw'), ['amount' => 50])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Your available balance is not enough for this withdrawal.']);

        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseCount('error_incidents', 0);
        $this->assertSame('25.00', $user->wallet()->firstOrFail()->cached_balance);
    }

    public function test_unexpected_withdrawal_failures_still_get_a_safe_reference_and_operation_context(): void
    {
        $user = $this->createPlayerWithWallet('100.00');
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->createKyc($user, KycStatus::APPROVED);
        $this->mock(RequestWithdrawalAction::class)->shouldReceive('execute')->once()->andThrow(new RuntimeException('Simulated withdrawal storage failure.'));
        Livewire::actingAs($user)->test(WalletDashboard::class)
            ->set('amount', '50.00')->call('withdraw')
            ->assertSee('Unable to create the withdrawal request. Reference: ERR-')
            ->assertDontSee('Simulated withdrawal storage failure.');
        $this->assertDatabaseCount('withdrawals', 0);
        $this->assertDatabaseHas('error_incidents', ['message' => 'Simulated withdrawal storage failure.']);
        $this->assertSame('wallet_withdrawal_request', ErrorIncident::query()->firstOrFail()->context['operation']);
    }

    private function createKyc(User $user, KycStatus $status): void
    {
        KycSubmission::query()->create([
            'uuid' => Str::uuid(), 'user_id' => $user->id, 'status' => $status,
            'document_type' => 'passport', 'document_paths' => ['kyc.png'],
        ]);
    }

    private function createPlayerWithWallet(string $balance): User
    {
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'username' => 'wallet-player',
            'email' => 'wallet-player@example.com',
            'password' => bcrypt('password'),
            'status' => UserStatus::ACTIVE,
        ]);
        $user->assignRole('PLAYER');

        Wallet::query()->create([
            'uuid' => Str::uuid()->toString(),
            'user_id' => $user->id,
            'cached_balance' => $balance,
            'status' => WalletStatus::ACTIVE,
        ]);

        return $user;
    }
}
