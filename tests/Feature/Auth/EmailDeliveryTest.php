<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Auth\PasswordReset;
use App\Livewire\Auth\Register;
use App\Modules\Identity\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_registration_sends_verification_email_and_blocks_dashboard_until_verified(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->set('username', 'verify_user')
            ->set('email', 'verify@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->set('accepted_policies', true)
            ->set('age_confirmed', true)
            ->call('register')
            ->assertRedirect('/verify-email');

        $user = User::query()->where('email', 'verify@example.com')->firstOrFail();

        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/verify-email');
    }

    public function test_forgot_password_sends_reset_link_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
        ]);

        Livewire::test(PasswordReset::class)
            ->set('email', 'reset@example.com')
            ->call('requestReset')
            ->assertHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }
}
