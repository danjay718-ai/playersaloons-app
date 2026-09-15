<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\KycAdmin;
use App\Livewire\Admin\RolePermissionAdmin;
use App\Livewire\Admin\TournamentAdmin;
use App\Livewire\Admin\UserAdmin;
use App\Livewire\Admin\WithdrawalAdmin;
use App\Modules\Identity\Actions\AssignRoleAction;
use App\Modules\Identity\Actions\TransferSuperAdminAction;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_is_blocked_from_admin_panel(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/login');
    }

    public function test_player_is_blocked_from_admin_panel(): void
    {
        $player = User::factory()->create();
        $player->assignRole('PLAYER');

        $this->actingAs($player)->get('/admin')->assertStatus(403);
        $this->actingAs($player)->get('/admin/tournaments')->assertStatus(403);
        $this->actingAs($player)->get('/admin/kyc')->assertStatus(403);
    }

    public function test_tournament_organizer_can_access_tournaments_but_blocked_from_kyc_and_withdrawals(): void
    {
        $organizer = User::factory()->create();
        $organizer->assignRole('TOURNAMENT_ORGANIZER');

        // Can access tournaments
        $this->actingAs($organizer);
        Livewire::test(TournamentAdmin::class)->assertOk();

        // Blocked from KYC (403)
        Livewire::test(KycAdmin::class)->assertForbidden();

        // Blocked from Withdrawals (403)
        Livewire::test(WithdrawalAdmin::class)->assertForbidden();

        // Blocked from User Directory (403)
        Livewire::test(UserAdmin::class)->assertForbidden();

        // Blocked from Roles & Permissions (403)
        Livewire::test(RolePermissionAdmin::class)->assertForbidden();
    }

    public function test_super_admin_has_full_unrestricted_access(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('SUPER_ADMIN');

        $this->actingAs($superAdmin);

        Livewire::test(TournamentAdmin::class)->assertOk();
        Livewire::test(KycAdmin::class)->assertOk();
        Livewire::test(WithdrawalAdmin::class)->assertOk();
        Livewire::test(UserAdmin::class)->assertOk();
        Livewire::test(RolePermissionAdmin::class)->assertOk();
    }

    public function test_roles_page_is_readable_but_its_crud_actions_still_require_roles_manage(): void
    {
        $moderator = User::factory()->create();
        $moderator->assignRole('MODERATOR');
        $moderator->givePermissionTo('roles.view');

        $this->actingAs($moderator);

        Livewire::test(RolePermissionAdmin::class)
            ->assertOk()
            ->assertDontSee('Add Role')
            ->call('createRole', 'UNAUTHORIZED_ROLE')
            ->assertForbidden();
    }

    public function test_assign_role_action_rejects_direct_super_admin_assignment(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('SUPER_ADMIN');

        $target = User::factory()->create();
        $target->assignRole('ADMIN');

        $this->expectException(InvalidArgumentException::class);
        app(AssignRoleAction::class)->execute($target, 'SUPER_ADMIN', $superAdmin);
    }

    public function test_transfer_super_admin_action_transfers_ownership_atomically(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('SUPER_ADMIN');

        $target = User::factory()->create();
        $target->assignRole('ADMIN');

        app(TransferSuperAdminAction::class)->execute($target, $superAdmin);

        $this->assertTrue($target->fresh()->hasRole('SUPER_ADMIN'));
        $this->assertFalse($superAdmin->fresh()->hasRole('SUPER_ADMIN'));
        $this->assertTrue($superAdmin->fresh()->hasRole('ADMIN'));
    }

    public function test_role_admin_can_create_rename_and_delete_custom_roles(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('SUPER_ADMIN');

        $this->actingAs($superAdmin);

        // Create custom role
        Livewire::test(RolePermissionAdmin::class)
            ->set('newRoleName', 'EVENT_COORDINATOR')
            ->call('createRole')
            ->assertHasNoErrors();

        $role = Role::where('name', 'EVENT_COORDINATOR')->first();
        $this->assertNotNull($role);

        // Rename custom role
        Livewire::test(RolePermissionAdmin::class)
            ->call('openEditRole', $role->id)
            ->set('editRoleName', 'LEAD_COORDINATOR')
            ->call('updateRoleName')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('roles', ['name' => 'LEAD_COORDINATOR']);

        // Delete custom role
        $updatedRole = Role::where('name', 'LEAD_COORDINATOR')->first();
        Livewire::test(RolePermissionAdmin::class)
            ->call('openDeleteRole', $updatedRole->id)
            ->call('confirmDeleteRole')
            ->assertHasNoErrors();

        $this->assertSoftDeleted('roles', ['name' => 'LEAD_COORDINATOR']);
        $this->assertNull(\App\Modules\Identity\Models\Role::where('name', 'LEAD_COORDINATOR')->first());
    }

    public function test_protected_roles_cannot_be_renamed_or_deleted(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('SUPER_ADMIN');

        $this->actingAs($superAdmin);

        $adminRole = Role::where('name', 'ADMIN')->first();

        // Renaming protected role is rejected and modal is not opened
        Livewire::test(RolePermissionAdmin::class)
            ->call('openEditRole', $adminRole->id)
            ->assertSet('showEditModal', false);

        // Deleting protected role is rejected and modal is not opened
        Livewire::test(RolePermissionAdmin::class)
            ->call('openDeleteRole', $adminRole->id)
            ->assertSet('showDeleteModal', false);

        $this->assertDatabaseHas('roles', ['name' => 'ADMIN']);
    }
}
