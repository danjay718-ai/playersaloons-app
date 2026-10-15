<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Livewire\Admin\UserAdmin;
use App\Modules\Identity\Actions\ChangeUserStatusAction;
use App\Modules\Identity\Actions\UnsuspendUserAction;
use App\Modules\Identity\Models\User;
use App\Modules\Operations\Models\Activity;
use App\Shared\Enums\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->admin->assignRole('ADMIN');
        $this->player = User::factory()->create(['status' => UserStatus::ACTIVE]);
        $this->player->assignRole('PLAYER');
        Event::fake();
    }

    public function test_admin_can_ban_and_restore_from_the_directory_with_recorded_reasons(): void
    {
        $page = Livewire::actingAs($this->admin)->test(UserAdmin::class)
            ->call('selectUser', $this->player->id)
            ->assertSee('Ban Account')->assertSee('Suspend Account')
            ->call('changeStatus', $this->player->id, 'banned', 'Verified repeated rule violations')
            ->assertHasNoErrors()->assertDispatched('user-status-updated')
            ->assertSee('Verified repeated rule violations')->assertSee('Restore Account');
        self::assertSame(UserStatus::BANNED, $this->player->fresh()->status);
        $audit = Activity::query()->where('description', 'user_banned')->firstOrFail();
        self::assertSame('active', $audit->properties['from_status']);
        self::assertSame('banned', $audit->properties['to_status']);
        self::assertSame($this->admin->id, $audit->causer_id);
        $page->call('changeStatus', $this->player->id, 'active', 'Appeal reviewed and approved')
            ->assertHasNoErrors()->assertSee('Appeal reviewed and approved');
        self::assertSame(UserStatus::ACTIVE, $this->player->fresh()->status);
        self::assertSame(1, Activity::query()->where('description', 'user_banned')->count());
        self::assertSame(1, Activity::query()->where('description', 'user_unbanned')->count());
    }

    public function test_reason_is_required_for_restriction_and_restoration(): void
    {
        Livewire::actingAs($this->admin)->test(UserAdmin::class)
            ->call('changeStatus', $this->player->id, 'banned', '   ')
            ->assertHasErrors('statusReason');
        self::assertSame(UserStatus::ACTIVE, $this->player->fresh()->status);
        $this->player->update(['status' => UserStatus::BANNED]);
        Livewire::test(UserAdmin::class)->call('changeStatus', $this->player->id, 'active', '')
            ->assertHasErrors('statusReason');
        self::assertSame(UserStatus::BANNED, $this->player->fresh()->status);
    }

    public function test_invalid_status_and_invalid_transition_do_not_change_the_account(): void
    {
        Livewire::actingAs($this->admin)->test(UserAdmin::class)
            ->call('changeStatus', $this->player->id, 'deleted', 'Invalid status request')
            ->assertHasErrors('status');
        $this->player->update(['status' => UserStatus::BANNED]);
        Livewire::test(UserAdmin::class)->call('changeStatus', $this->player->id, 'suspended', 'Cannot downgrade a ban')
            ->assertHasErrors('statusReason');
        self::assertSame(UserStatus::BANNED, $this->player->fresh()->status);
    }

    #[DataProvider('protectedTargets')]
    public function test_status_action_protects_self_super_admin_and_unauthorized_staff(string $case): void
    {
        $actor = $this->admin;
        $target = $this->player;
        if (str_starts_with($case, 'super_actor')) {
            $actor->syncRoles(['SUPER_ADMIN']);
        }
        if (in_array($case, ['self', 'super_actor_self'], true)) {
            $target = $actor;
        } elseif (in_array($case, ['super_admin', 'super_actor_target'], true)) {
            $target->assignRole('SUPER_ADMIN');
        } else {
            $actor->syncRoles(['SUPPORT_AGENT']);
        }
        $this->expectException(AuthorizationException::class);
        try {
            app(ChangeUserStatusAction::class)->execute($target, $actor, UserStatus::BANNED, 'Unauthorized status update');
        } finally {
            self::assertSame(UserStatus::ACTIVE, $target->fresh()->status);
        }
    }

    public static function protectedTargets(): array
    {
        return [['self'], ['super_admin'], ['unauthorized'], ['super_actor_self'], ['super_actor_target']];
    }

    public function test_legacy_unsuspend_cannot_silently_remove_a_ban(): void
    {
        $this->player->update(['status' => UserStatus::BANNED]);
        $this->expectException(LogicException::class);
        app(UnsuspendUserAction::class)->execute($this->player, $this->admin);
    }

    public function test_unrecorded_ban_is_explicitly_shown_without_inventing_a_reason(): void
    {
        $this->player->update(['status' => UserStatus::BANNED]);
        Livewire::actingAs($this->admin)->test(UserAdmin::class)->call('selectUser', $this->player->id)
            ->assertSee('No recorded reason is available for this account status.');
    }

    public function test_banning_an_already_signed_in_player_ends_access_on_the_next_request(): void
    {
        $this->actingAs($this->player);
        app(ChangeUserStatusAction::class)->execute($this->player, $this->admin, UserStatus::BANNED, 'Verified account restriction');
        // A session guard reloads the account from storage on the next HTTP request.
        $this->player->refresh();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_suspension_reason_and_restore_actions_remain_available(): void
    {
        Livewire::actingAs($this->admin)->test(UserAdmin::class)
            ->call('changeStatus', $this->player->id, 'suspended', 'Temporary investigation hold')
            ->assertHasNoErrors()->call('selectUser', $this->player->id)
            ->assertSee('Temporary investigation hold')->assertSee('Ban Account')->assertSee('Restore Account')
            ->call('changeStatus', $this->player->id, 'active', 'Investigation completed')
            ->assertHasNoErrors();
        self::assertSame(UserStatus::ACTIVE, $this->player->fresh()->status);
    }
}
