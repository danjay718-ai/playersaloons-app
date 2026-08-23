<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Modules\Identity\Models\User;
use Database\Seeders\PlayerAccountSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlayerAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_account_seeder_creates_1000_players_with_correct_credentials_and_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PlayerAccountSeeder::class);

        $this->assertEquals(1000, User::query()->count());

        $player1 = User::query()->where('email', 'player1@example.com')->first();
        $this->assertNotNull($player1);
        $this->assertEquals('player1', $player1->username);
        $this->assertTrue(Hash::check('Password123', $player1->password));
        $this->assertTrue($player1->hasRole('PLAYER'));
        $this->assertNotNull($player1->profile);
        $this->assertNotNull($player1->wallet);

        $player1000 = User::query()->where('email', 'player1000@example.com')->first();
        $this->assertNotNull($player1000);
        $this->assertEquals('player1000', $player1000->username);
        $this->assertTrue(Hash::check('Password123', $player1000->password));
        $this->assertTrue($player1000->hasRole('PLAYER'));
        $this->assertNotNull($player1000->profile);
        $this->assertNotNull($player1000->wallet);
    }
}
