<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Livewire\Auth\Register;
use App\Modules\Compliance\Models\BlockedCountry;
use App\Modules\Compliance\Services\CountryEligibilityService;
use App\Modules\Identity\Actions\RegisterUserAction;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RegisterUserActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        app(CountryEligibilityService::class)->forget();
        parent::tearDown();
    }

    public function test_player_can_register_successfully(): void
    {
        Event::fake([UserRegistered::class]);

        $user = app(RegisterUserAction::class)->execute([
            'email' => 'player@example.com',
            'username' => 'player_one',
            'password' => 'secret-password',
            'full_name' => 'Player Legal Name',
            'display_name' => 'Player One',
            'country_code' => 'PH',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->hasRole('PLAYER'));

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'email' => 'player@example.com',
            'username' => 'player_one',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->getKey(),
            'full_name' => 'Player Legal Name',
            'display_name' => 'Player One',
            'country_code' => 'PH',
        ]);

        Event::assertDispatched(UserRegistered::class, function (UserRegistered $e) use ($user): bool {
            return $e->userId === (int) $user->getKey()
                && $e->email === 'player@example.com'
                && $e->username === 'player_one';
        });
    }

    public function test_wallet_is_created_after_registration(): void
    {
        $user = app(RegisterUserAction::class)->execute([
            'email' => 'player2@example.com',
            'username' => 'player_two',
            'password' => 'secret-password',
        ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->getKey(),
            'cached_balance' => '0.00',
            'status' => 'active',
        ]);
    }

    public function test_registration_fails_with_invalid_email(): void
    {
        Livewire::test(Register::class)
            ->set('username', 'validuser')
            ->set('email', 'not-an-email')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasErrors(['email']);
    }

    public function test_registration_fails_with_existing_username(): void
    {
        app(RegisterUserAction::class)->execute([
            'email' => 'first@example.com',
            'username' => 'taken_user',
            'password' => 'secret-password',
        ]);

        Livewire::test(Register::class)
            ->set('username', 'taken_user')
            ->set('email', 'second@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasErrors(['username']);
    }

    public function test_registration_requires_policy_acceptance_and_age_confirmation(): void
    {
        Livewire::test(Register::class)
            ->set('username', 'consent_user')
            ->set('email', 'consent@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasErrors([
                'accepted_policies',
                'age_confirmed',
            ]);
    }

    public function test_registration_stores_policy_age_and_newsletter_consent(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->set('username', 'consented_user')
            ->set('full_name', 'Consented User')
            ->set('countryCode', 'PH')
            ->set('email', 'consented@example.com')
            ->set('password', 'Valid123')
            ->set('password_confirmation', 'Valid123')
            ->set('accepted_policies', true)
            ->set('age_confirmed', true)
            ->set('newsletter_subscribed', true)
            ->call('register')
            ->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'consented@example.com')->firstOrFail();

        $this->assertNotNull($user->accepted_terms_at);
        $this->assertNotNull($user->accepted_privacy_policy_at);
        $this->assertNotNull($user->accepted_cookie_policy_at);
        $this->assertNotNull($user->age_confirmed_at);
        $this->assertTrue($user->newsletter_subscribed);
        $this->assertNotNull($user->newsletter_subscribed_at);
        $this->assertSame('Consented User', $user->profile?->full_name);
        $this->assertSame('consented_user', $user->profile?->display_name);
        $this->assertSame('PH', $user->profile?->country_code);
        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_registration_rejects_weak_passwords(): void
    {
        Livewire::test(Register::class)
            ->set('username', 'secure_user')
            ->set('full_name', 'Secure User')
            ->set('countryCode', 'PH')
            ->set('email', 'secure@example.com')
            ->set('password', 'weakpassword')
            ->set('password_confirmation', 'weakpassword')
            ->set('accepted_policies', true)
            ->set('age_confirmed', true)
            ->call('register')
            ->assertHasErrors(['password']);
    }

    public function test_registration_requires_full_name_and_country(): void
    {
        Livewire::test(Register::class)
            ->set('username', 'identity_user')
            ->set('email', 'identity@example.com')
            ->set('password', 'Valid123')
            ->set('password_confirmation', 'Valid123')
            ->set('accepted_policies', true)
            ->set('age_confirmed', true)
            ->call('register')
            ->assertHasErrors(['full_name', 'countryCode']);
    }

    public function test_blocked_country_is_not_selectable_or_accepted(): void
    {
        BlockedCountry::query()->create([
            'country_code' => 'PH',
            'country_name' => 'Philippines',
        ]);
        app(CountryEligibilityService::class)->forget();

        Livewire::test(Register::class)
            ->assertDontSee('Philippines')
            ->set('countryCode', 'PH')
            ->call('register')
            ->assertHasErrors(['countryCode']);
    }
}
