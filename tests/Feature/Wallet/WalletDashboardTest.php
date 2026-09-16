<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Livewire\Wallet\WalletDashboard;
use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\StripeCheckoutService;
use App\Shared\Enums\UserStatus;
use App\Shared\Enums\WalletStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
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

        $this->assertSame('Deposits are temporarily unavailable. Please try again later.', session('info'));
        $this->assertFalse(session()->has('error'));
        $this->assertSame('25.00', $user->wallet()->first()?->cached_balance);
        $this->assertDatabaseHas('error_incidents', [
            'message' => 'Stripe secret key is not configured.',
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
