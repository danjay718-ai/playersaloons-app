<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Auth\CompleteGoogleRegistration;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.google_auth.enabled' => true,
            'services.google.client_id' => 'google-test-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'google-test-client-secret',
            'services.google.redirect_uri' => null,
        ]);

        Http::preventStrayRequests();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_and_register_pages_show_google_button_and_gracefully_handle_missing_credentials(): void
    {
        $this->get('/login')->assertOk()->assertSee('Continue with Google');
        $this->get('/register')->assertOk()->assertSee('Continue with Google');

        config(['services.google.client_id' => '', 'services.google.client_secret' => '']);

        $this->get(route('auth.google.redirect'))
            ->assertRedirect('/login')
            ->assertSessionHas('error');
    }

    public function test_new_user_signs_up_with_google_via_compliance_completion_step(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.google_new_user_token',
                'expires_in' => 3600,
                'scope' => 'openid email profile',
                'token_type' => 'Bearer',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-sub-new-1001',
                'email' => 'NewPlayer@Example.com',
                'email_verified' => true,
                'name' => 'Alex Rivera',
            ]),
        ]);

        $redirect = $this->get(route('auth.google.redirect'));
        $redirect->assertRedirect();
        $location = (string) $redirect->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertStringContainsString('code_challenge_method=S256', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $state = (string) ($params['state'] ?? '');
        $this->assertNotSame('', $state);

        $this->get(route('auth.google.callback', ['code' => 'google-code-1', 'state' => $state]))
            ->assertRedirect(route('auth.google.complete'));

        Livewire::test(CompleteGoogleRegistration::class)
            ->assertSet('googleEmail', 'newplayer@example.com')
            ->assertSet('full_name', 'Alex Rivera')
            ->set('username', 'alex_rivera')
            ->set('countryCode', 'US')
            ->set('accepted_policies', true)
            ->set('age_confirmed', true)
            ->call('complete')
            ->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'newplayer@example.com')->firstOrFail();
        $this->assertSame('alex_rivera', $user->username);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->age_confirmed_at);
        $this->assertNotNull($user->accepted_terms_at);
        $this->assertTrue($user->hasRole('PLAYER'));
        $this->assertNotNull($user->wallet);
        $this->assertAuthenticatedAs($user);

        $googleAccount = $user->googleAccount()->firstOrFail();
        $this->assertSame('google-sub-new-1001', $googleAccount->external_user_id);
        $this->assertSame('newplayer@example.com', $googleAccount->external_username);

        // Token must be encrypted in the database and hidden from serialization.
        $rawToken = DB::table('external_provider_accounts')->where('id', $googleAccount->id)->value('access_token');
        $this->assertNotSame('ya29.google_new_user_token', $rawToken);
        $this->assertArrayNotHasKey('access_token', $googleAccount->toArray());
    }

    public function test_existing_player_auto_links_verified_google_email_and_enforces_2fa_and_bans(): void
    {
        $player = User::factory()->create([
            'email' => 'existing@example.com',
            'status' => UserStatus::ACTIVE,
            'email_verified_at' => null,
        ]);
        $player->assignRole('PLAYER');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.existing_token',
                'expires_in' => 3600,
                'scope' => 'openid email profile',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-sub-existing-2002',
                'email' => 'existing@example.com',
                'email_verified' => true,
                'name' => 'Existing Player',
            ]),
        ]);

        $redirect = $this->get(route('auth.google.redirect'));
        parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $params);

        $this->get(route('auth.google.callback', ['code' => 'code-existing', 'state' => $params['state']]))
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($player);
        $this->assertNotNull($player->fresh()->email_verified_at);
        $this->assertSame('google-sub-existing-2002', $player->googleAccount()->first()?->external_user_id);

        // Now enable 2FA -> Google login must still route to 2FA challenge.
        $this->post('/logout');
        $player->forceFill([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $redirect2 = $this->get(route('auth.google.redirect'));
        parse_str((string) parse_url((string) $redirect2->headers->get('Location'), PHP_URL_QUERY), $params2);

        $this->get(route('auth.google.callback', ['code' => 'code-2fa', 'state' => $params2['state']]))
            ->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        // Banned user is rejected.
        $player->forceFill(['status' => UserStatus::BANNED])->save();
        $redirect3 = $this->get(route('auth.google.redirect'));
        parse_str((string) parse_url((string) $redirect3->headers->get('Location'), PHP_URL_QUERY), $params3);

        $this->get(route('auth.google.callback', ['code' => 'code-banned', 'state' => $params3['state']]))
            ->assertRedirect('/login')
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_unverified_google_email_and_admin_email_auto_linking_are_blocked(): void
    {
        $admin = User::factory()->create([
            'email' => 'staff@example.com',
            'status' => UserStatus::ACTIVE,
        ]);
        $admin->assignRole('ADMIN');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.security_test',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::sequence()
                ->push([
                    'sub' => 'google-sub-unverified',
                    'email' => 'unverified@example.com',
                    'email_verified' => false,
                    'name' => 'Unverified',
                ])
                ->push([
                    'sub' => 'google-sub-admin-attempt',
                    'email' => 'staff@example.com',
                    'email_verified' => true,
                    'name' => 'Staff',
                ]),
        ]);

        // Unverified email rejected
        $redirect1 = $this->get(route('auth.google.redirect'));
        parse_str((string) parse_url((string) $redirect1->headers->get('Location'), PHP_URL_QUERY), $params1);
        $this->get(route('auth.google.callback', ['code' => 'code-u', 'state' => $params1['state']]))
            ->assertRedirect('/login')
            ->assertSessionHas('error');
        $this->assertGuest();

        // Admin email auto-link rejected
        $redirect2 = $this->get(route('auth.google.redirect'));
        parse_str((string) parse_url((string) $redirect2->headers->get('Location'), PHP_URL_QUERY), $params2);
        $this->get(route('auth.google.callback', ['code' => 'code-a', 'state' => $params2['state']]))
            ->assertRedirect('/login')
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_soft_deleted_user_email_is_handled_gracefully_without_500_error(): void
    {
        $deletedUser = User::factory()->create([
            'email' => 'archived@example.com',
            'status' => UserStatus::ACTIVE,
        ]);
        $deletedUser->delete();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.archived_token',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-sub-archived',
                'email' => 'archived@example.com',
                'email_verified' => true,
                'name' => 'Archived Player',
            ]),
        ]);

        $redirect = $this->get(route('auth.google.redirect'));
        parse_str((string) parse_url((string) $redirect->headers->get('Location'), PHP_URL_QUERY), $params);

        $this->get(route('auth.google.callback', ['code' => 'code-archived', 'state' => $params['state']]))
            ->assertRedirect('/login')
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_authenticated_player_can_link_and_disconnect_google_and_duplicates_are_blocked(): void
    {
        $playerA = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $playerA->assignRole('PLAYER');
        $playerB = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $playerB->assignRole('PLAYER');

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.link_token',
                'expires_in' => 3600,
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub' => 'google-sub-shared-9009',
                'email' => 'shared@example.com',
                'email_verified' => true,
                'name' => 'Shared User',
            ]),
        ]);

        // Player A links Google from Profile
        $redirectA = $this->actingAs($playerA)->get(route('auth.google.redirect'));
        parse_str((string) parse_url((string) $redirectA->headers->get('Location'), PHP_URL_QUERY), $paramsA);

        $this->actingAs($playerA)
            ->get(route('auth.google.callback', ['code' => 'code-link-a', 'state' => $paramsA['state']]))
            ->assertRedirect('/profile')
            ->assertSessionHas('success');

        $this->assertSame('google-sub-shared-9009', $playerA->googleAccount()->first()?->external_user_id);

        // Player B cannot link the same Google account
        $redirectB = $this->actingAs($playerB)->get(route('auth.google.redirect'));
        parse_str((string) parse_url((string) $redirectB->headers->get('Location'), PHP_URL_QUERY), $paramsB);

        $this->actingAs($playerB)
            ->get(route('auth.google.callback', ['code' => 'code-link-b', 'state' => $paramsB['state']]))
            ->assertRedirect('/profile')
            ->assertSessionHas('error');

        $this->assertNull($playerB->googleAccount()->first());

        // Player A disconnects Google
        $this->actingAs($playerA)
            ->delete(route('auth.google.disconnect'))
            ->assertRedirect('/profile')
            ->assertSessionHas('success');

        $this->assertNull($playerA->googleAccount()->first());
    }
}
