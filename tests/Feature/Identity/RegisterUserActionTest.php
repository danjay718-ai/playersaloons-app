<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Actions\RegisterUserAction;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterUserActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_user_profile_role_and_wallet(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

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

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->getKey(),
            'cached_balance' => '0',
            'status' => 'active',
        ]);
    }
}
