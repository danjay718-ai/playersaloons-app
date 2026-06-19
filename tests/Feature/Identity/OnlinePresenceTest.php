<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Http\Middleware\UpdateUserOnlineStatus;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnlinePresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'uuid'              => Str::uuid()->toString(),
            'email'             => 'player@example.com',
            'username'          => 'player_one',
            'password'          => bcrypt('password'),
            'status'            => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    public function test_middleware_sets_redis_key_for_authenticated_user(): void
    {
        $user = $this->makeUser();

        Redis::shouldReceive('setex')
            ->once()
            ->with('user_online:'.$user->id, 300, 1);

        $this->actingAs($user)->get('/dashboard');
    }

    public function test_middleware_does_not_set_key_for_guest(): void
    {
        Redis::shouldReceive('setex')->never();

        $this->get('/');
    }

    public function test_is_online_returns_true_when_redis_key_exists(): void
    {
        $user = $this->makeUser();

        Redis::shouldReceive('exists')
            ->once()
            ->with('user_online:'.$user->id)
            ->andReturn(1);

        $this->assertTrue($user->isOnline());
    }

    public function test_is_online_returns_false_when_redis_key_missing(): void
    {
        $user = $this->makeUser();

        Redis::shouldReceive('exists')
            ->once()
            ->with('user_online:'.$user->id)
            ->andReturn(0);

        $this->assertFalse($user->isOnline());
    }
}
