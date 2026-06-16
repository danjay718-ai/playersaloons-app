<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Actions\RegisterUserAction;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    public function test_player_can_register_successfully(): void
    {
        Event::fake([UserRegistered::class]);

        $user = app(RegisterUserAction::class)->execute([
            'email' => 'player@example.com',
            'username' => 'player_one',
            'password' => 'secret-password',
            'display_name' => 'Player One',
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
            'display_name' => 'Player One',
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
        Livewire::test(\App\Livewire\Auth\Register::class)
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

        Livewire::test(\App\Livewire\Auth\Register::class)
            ->set('username', 'taken_user')
            ->set('email', 'second@example.com')
            ->set('password', 'secret-password')
            ->set('password_confirmation', 'secret-password')
            ->call('register')
            ->assertHasErrors(['username']);
    }
}
