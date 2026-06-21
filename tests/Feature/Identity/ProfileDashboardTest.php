<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Livewire\Profile\ProfileDashboard;
use App\Modules\Identity\Events\EmailVerified;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->unverified()->create([
            'username' => 'oldhandle',
            'email' => 'old@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->user->assignRole('PLAYER');
    }

    public function test_player_profile_page_renders_game_profile_without_inline_kyc_form(): void
    {
        $this->actingAs($this->user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Player Card')
            ->assertSee('NOT VERIFIED')
            ->assertSee('Verify')
            ->assertSee('Profile')
            ->assertSee('Account')
            ->assertSee('Security')
            ->assertSee('Comms');
    }

    public function test_player_can_update_public_profile_info(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfileDashboard::class)
            ->set('displayName', 'Arcade Ace')
            ->set('bio', 'Main stage grinder.')
            ->set('countryCode', 'PH')
            ->set('timezone', 'Asia/Manila')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $this->user->id,
            'display_name' => 'Arcade Ace',
            'bio' => 'Main stage grinder.',
            'country_code' => 'PH',
            'timezone' => 'Asia/Manila',
        ]);
    }

    public function test_player_can_update_account_and_email_requires_reverification(): void
    {
        $this->user->forceFill(['email_verified_at' => now()])->save();

        Livewire::actingAs($this->user)
            ->test(ProfileDashboard::class)
            ->set('username', 'newhandle')
            ->set('email', 'new@example.com')
            ->call('updateAccount')
            ->assertHasNoErrors();

        $this->user->refresh();

        $this->assertSame('newhandle', $this->user->username);
        $this->assertSame('new@example.com', $this->user->email);
        $this->assertNull($this->user->email_verified_at);
    }

    public function test_player_can_verify_email_from_profile(): void
    {
        Event::fake([EmailVerified::class]);

        Livewire::actingAs($this->user)
            ->test(ProfileDashboard::class)
            ->call('verifyEmail');

        $this->assertNotNull($this->user->fresh()->email_verified_at);
        Event::assertDispatched(EmailVerified::class);
    }

    public function test_player_can_change_password(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfileDashboard::class)
            ->set('currentPassword', 'password')
            ->set('newPassword', 'new-password')
            ->set('newPasswordConfirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password', (string) $this->user->fresh()->password));
    }

    public function test_comms_loadout_preferences_are_persisted(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfileDashboard::class)
            ->assertSee('Comms Loadout')
            ->call('updateNotificationPreference', 'emailNotifications', false)
            ->call('updateNotificationPreference', 'inAppNotifications', false)
            ->call('updateNotificationPreference', 'realtimeNotifications', true)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $this->user->id,
            'email_enabled' => false,
            'in_app_enabled' => false,
            'realtime_enabled' => true,
        ]);
    }
}
