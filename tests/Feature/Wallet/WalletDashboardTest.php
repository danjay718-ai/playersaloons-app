<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Livewire\Wallet\WalletDashboard;
use App\Modules\Identity\Models\User;
use App\Modules\Wallet\Models\Wallet;
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

    public function test_deposit_updates_rendered_balance_without_page_refresh(): void
    {
        $user = $this->createPlayerWithWallet('25.00');

        Livewire::actingAs($user)
            ->test(WalletDashboard::class)
            ->assertSee('$25.00')
            ->set('depositAmount', '50')
            ->call('deposit')
            ->assertSee('Successfully deposited $50.00 to your wallet!')
            ->assertSee('$75.00')
            ->assertSee('Mock Deposit via Web Dashboard');

        $this->assertSame('75.00', $user->wallet()->first()?->cached_balance);
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
