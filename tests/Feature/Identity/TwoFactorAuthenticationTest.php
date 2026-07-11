<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Profile\ProfileDashboard;
use App\Modules\Identity\Actions\EnableTwoFactorAction;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TotpService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->player = User::factory()->create([
            'email' => 'player@example.com',
            'username' => 'player',
            'password' => Hash::make('password'),
        ]);
        $this->player->assignRole('PLAYER');
    }

    public function test_totp_service_generates_and_verifies_codes(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();

        $this->assertTrue($totp->verify($secret, $totp->code($secret)));
        $this->assertFalse($totp->verify($secret, '000000'));
    }

    public function test_player_can_enable_two_factor_from_profile(): void
    {
        $component = Livewire::actingAs($this->player)
            ->test(ProfileDashboard::class)
            ->call('beginTwoFactorSetup');

        $secret = $component->get('twoFactorSetupSecret');
        $component
            ->set('twoFactorCode', app(TotpService::class)->code($secret))
            ->call('confirmTwoFactor')
            ->assertHasNoErrors()
            ->assertSet('twoFactorRecoveryCodes', fn (array $codes) => count($codes) === 8);

        $this->assertNotNull($this->player->fresh()->two_factor_confirmed_at);
    }

    public function test_enabled_two_factor_requires_challenge_after_password(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        app(EnableTwoFactorAction::class)->execute($this->player, $secret, app(TotpService::class)->code($secret));

        Livewire::test(Login::class)
            ->set('identity', 'player@example.com')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect('/two-factor-challenge');

        $this->assertGuest();

        Livewire::test(TwoFactorChallenge::class)
            ->set('code', app(TotpService::class)->code($secret))
            ->call('verify')
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->player);
    }

    public function test_recovery_code_is_single_use(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        $codes = app(EnableTwoFactorAction::class)->execute($this->player, $secret, app(TotpService::class)->code($secret));
        session()->put('two_factor_login', ['id' => $this->player->id, 'remember' => false]);

        Livewire::test(TwoFactorChallenge::class)
            ->set('useRecoveryCode', true)
            ->set('code', $codes[0])
            ->call('verify')
            ->assertRedirect('/dashboard');

        $this->assertCount(7, $this->player->fresh()->two_factor_recovery_codes);

        Auth()->logout();
        session()->put('two_factor_login', ['id' => $this->player->id, 'remember' => false]);
        Livewire::test(TwoFactorChallenge::class)
            ->set('useRecoveryCode', true)
            ->set('code', $codes[0])
            ->call('verify')
            ->assertHasErrors('code');
    }

    public function test_player_can_disable_two_factor_with_password(): void
    {
        $secret = app(TotpService::class)->generateSecret();
        app(EnableTwoFactorAction::class)->execute($this->player, $secret, app(TotpService::class)->code($secret));

        Livewire::actingAs($this->player)
            ->test(ProfileDashboard::class)
            ->set('twoFactorPassword', 'password')
            ->call('disableTwoFactor')
            ->assertHasNoErrors();

        $this->assertNull($this->player->fresh()->two_factor_confirmed_at);
    }
}
