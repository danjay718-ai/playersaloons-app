<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Livewire\Admin\ComplianceAdmin;
use App\Modules\Identity\Actions\ApplyComplianceBlockAction;
use App\Modules\Identity\Actions\RevokeComplianceBlockAction;
use App\Modules\Identity\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComplianceBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('ADMIN');
        $this->player = User::factory()->create();
        $this->player->assignRole('PLAYER');
    }

    public function test_admin_can_apply_and_revoke_compliance_block(): void
    {
        $block = app(ApplyComplianceBlockAction::class)->execute(
            $this->player,
            $this->admin,
            'fraud',
            'Repeated payment ownership mismatch.',
        );

        $this->assertTrue($this->player->complianceBlocks()->active()->whereKey($block->id)->exists());

        app(RevokeComplianceBlockAction::class)->execute($block, $this->admin, 'Identity review completed.');

        $this->assertFalse($this->player->complianceBlocks()->active()->exists());
        $this->assertNotNull($block->fresh()->revoked_at);
    }

    public function test_non_admin_cannot_apply_compliance_block(): void
    {
        $otherPlayer = User::factory()->create();
        $otherPlayer->assignRole('PLAYER');

        $this->expectException(AuthorizationException::class);
        app(ApplyComplianceBlockAction::class)->execute($otherPlayer, $this->player, 'fraud', 'Unauthorized attempt.');
    }

    public function test_active_block_prevents_player_route_access(): void
    {
        app(ApplyComplianceBlockAction::class)->execute($this->player, $this->admin, 'platform_abuse', 'Confirmed platform abuse incident.');

        $this->actingAs($this->player)->get('/dashboard')
            ->assertForbidden()
            ->assertSee('Access is prohibited by policy rules');
    }

    public function test_expired_block_does_not_prevent_access(): void
    {
        $this->player->complianceBlocks()->create([
            'uuid' => fake()->uuid(),
            'created_by' => $this->admin->id,
            'category' => 'identity_risk',
            'reason' => 'Temporary identity review.',
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($this->player)->get('/dashboard')->assertOk();
    }

    public function test_admin_component_can_apply_block(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ComplianceAdmin::class)
            ->call('openApply', (string) $this->player->id)
            ->set('category', 'chargeback')
            ->set('reason', 'Confirmed unresolved payment chargeback.')
            ->call('applyBlock')
            ->assertHasNoErrors()
            ->assertSee('Confirmed unresolved payment chargeback.');

        $this->assertDatabaseHas('compliance_blocks', ['user_id' => $this->player->id, 'category' => 'chargeback']);
    }
}
