<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Livewire\Profile\ProfileDashboard;
use App\Modules\Compliance\Models\BlockedCountry;
use App\Modules\Compliance\Services\CountryEligibilityService;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
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

    protected function tearDown(): void
    {
        app(CountryEligibilityService::class)->forget();
        parent::tearDown();
    }

    public function test_player_profile_page_renders_game_profile_without_inline_kyc_form(): void
    {
        $this->user->forceFill(['email_verified_at' => now()])->save();

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

    public function test_uploaded_avatar_is_displayed_in_the_dashboard_topbar(): void
    {
        $this->user->forceFill(['email_verified_at' => now()])->save();
        $this->user->profile()->create([
            'uuid' => Str::uuid()->toString(),
            'display_name' => $this->user->username,
            'avatar_url' => '/storage/avatars/'.$this->user->id.'/avatar.webp',
        ]);

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeHtml('<img src="/storage/avatars/'.$this->user->id.'/avatar.webp" alt="oldhandle" class="h-full w-full object-cover">');
    }

    public function test_dashboard_topbar_falls_back_to_username_initials_without_an_avatar(): void
    {
        $this->user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($this->user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('OL');
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

    public function test_blocked_country_is_not_selectable_or_accepted_in_profile(): void
    {
        BlockedCountry::query()->create([
            'country_code' => 'PH',
            'country_name' => 'Philippines',
        ]);
        app(CountryEligibilityService::class)->forget();

        Livewire::actingAs($this->user)
            ->test(ProfileDashboard::class)
            ->assertDontSee('Philippines')
            ->set('countryCode', 'PH')
            ->call('updateProfile')
            ->assertHasErrors(['countryCode']);
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

    public function test_player_can_request_email_verification_from_profile(): void
    {
        Notification::fake();

        Livewire::actingAs($this->user)
            ->test(ProfileDashboard::class)
            ->call('resendEmailVerification')
            ->assertSee('Verification email sent. Check your inbox to complete verification.');

        $this->assertNull($this->user->fresh()->email_verified_at);
    }

    public function test_player_can_change_password(): void
    {
        Livewire::actingAs($this->user)
            ->test(ProfileDashboard::class)
            ->set('currentPassword', 'password')
            ->set('newPassword', 'Valid123')
            ->set('newPasswordConfirmation', 'Valid123')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('Valid123', (string) $this->user->fresh()->password));
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
