<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Livewire\Dashboard\PlayerDashboard;
use App\Modules\Identity\Models\User;
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

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlatformSystemUserSeeder::class);
        $this->seed(SystemSettingsSeeder::class);
    }

    private function createUser(string $role, string $email): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'uuid' => Str::uuid()->toString(),
            'email' => $email,
            'username' => explode('@', $email)[0],
            'password' => bcrypt('Password@1234!'),
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

    public function test_player_is_redirected_to_player_dashboard_after_login(): void
    {
        $this->createUser('PLAYER', 'player@test.com');

        Livewire::test(Login::class)
            ->set('identity', 'player@test.com')
            ->set('password', 'Password@1234!')
            ->call('login')
            ->assertRedirect('/dashboard');
    }

    public function test_super_admin_is_redirected_to_admin_panel_after_login(): void
    {
        $this->createUser('SUPER_ADMIN', 'superadmin@test.com');

        Livewire::test(Login::class)
            ->set('identity', 'superadmin@test.com')
            ->set('password', 'Password@1234!')
            ->call('login')
            ->assertRedirect('/admin');
    }

    public function test_admin_is_redirected_to_admin_panel_after_login(): void
    {
        $this->createUser('ADMIN', 'admin@test.com');

        Livewire::test(Login::class)
            ->set('identity', 'admin@test.com')
            ->set('password', 'Password@1234!')
            ->call('login')
            ->assertRedirect('/admin');
    }

    public function test_admin_visiting_player_dashboard_is_redirected_to_admin(): void
    {
        $admin = $this->createUser('ADMIN', 'admin2@test.com');

        // PlayerDashboard::render() issues a redirect()->to('/admin') for staff roles
        Livewire::actingAs($admin)
            ->test(PlayerDashboard::class)
            ->assertRedirect('/admin');
    }

    public function test_super_admin_visiting_player_dashboard_is_redirected_to_admin(): void
    {
        $superAdmin = $this->createUser('SUPER_ADMIN', 'superadmin2@test.com');

        Livewire::actingAs($superAdmin)
            ->test(PlayerDashboard::class)
            ->assertRedirect('/admin');
    }

    public function test_player_can_still_access_player_dashboard(): void
    {
        $player = $this->createUser('PLAYER', 'player2@test.com');

        $response = $this->actingAs($player)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSeeLivewire(PlayerDashboard::class);
    }

    public function test_invalid_credentials_show_error(): void
    {
        Livewire::test(Login::class)
            ->set('identity', 'nobody@test.com')
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['identity']);
    }
}
