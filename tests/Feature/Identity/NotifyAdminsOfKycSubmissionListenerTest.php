<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Community\Events\BroadcastNotification;
use App\Modules\Community\Services\NotificationService;
use App\Modules\Identity\Events\UserKycSubmitted;
use App\Modules\Identity\Listeners\NotifyAdminsOfKycSubmissionListener;
use App\Modules\Identity\Models\User;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotifyAdminsOfKycSubmissionListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeUser(string $email, string $username): User
    {
        return User::query()->create([
            'uuid'              => Str::uuid()->toString(),
            'email'             => $email,
            'username'          => $username,
            'password'          => bcrypt('password'),
            'status'            => UserStatus::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    private function listener(): NotifyAdminsOfKycSubmissionListener
    {
        return new NotifyAdminsOfKycSubmissionListener(
            $this->app->make(NotificationService::class)
        );
    }

    public function test_admins_are_notified_when_kyc_submitted(): void
    {
        Event::fake([BroadcastNotification::class]);

        $player = $this->makeUser('player@example.com', 'player_one');
        $admin  = $this->makeUser('admin@example.com', 'admin_one');
        $admin->assignRole('ADMIN');

        $this->listener()->handle(new UserKycSubmitted($player->id, 1));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type'    => 'kyc_submitted',
            'title'   => 'New KYC Submission',
        ]);
    }

    public function test_non_admin_users_are_not_notified(): void
    {
        Event::fake([BroadcastNotification::class]);

        $player      = $this->makeUser('player@example.com', 'player_one');
        $otherPlayer = $this->makeUser('player2@example.com', 'player_two');

        $this->listener()->handle(new UserKycSubmitted($player->id, 1));

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $otherPlayer->id,
            'type'    => 'kyc_submitted',
        ]);
    }

    public function test_all_admins_and_super_admins_are_notified(): void
    {
        Event::fake([BroadcastNotification::class]);

        $player     = $this->makeUser('player@example.com', 'player_one');
        $admin      = $this->makeUser('admin@example.com', 'admin_one');
        $superAdmin = $this->makeUser('super@example.com', 'super_one');
        $admin->assignRole('ADMIN');
        $superAdmin->assignRole('SUPER_ADMIN');

        $this->listener()->handle(new UserKycSubmitted($player->id, 1));

        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'kyc_submitted']);
        $this->assertDatabaseHas('notifications', ['user_id' => $superAdmin->id, 'type' => 'kyc_submitted']);
    }
}
