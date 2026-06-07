<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Team;

use App\Modules\Identity\Models\User;
use App\Modules\Team\Actions\AcceptTeamInvitationAction;
use App\Modules\Team\Actions\CreateTeamAction;
use App\Modules\Team\Actions\DeclineTeamInvitationAction;
use App\Modules\Team\Actions\DisbandTeamAction;
use App\Modules\Team\Actions\InviteToTeamAction;
use App\Modules\Team\Actions\RemoveTeamMemberAction;
use App\Modules\Team\Actions\RevokeTeamInvitationAction;
use App\Modules\Team\Actions\TransferTeamCaptainAction;
use App\Modules\Team\Actions\UpdateTeamAction;
use App\Modules\Team\Jobs\ExpireTeamInvitationsJob;
use App\Shared\Enums\TeamInvitationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TeamModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_team(): void
    {
        $user = User::factory()->create();
        $action = new CreateTeamAction;

        $team = $action->execute(['name' => 'My Awesome Team'], $user);

        $this->assertDatabaseHas('teams', [
            'name' => 'My Awesome Team',
            'captain_user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => 'captain',
        ]);
    }

    public function test_can_update_team(): void
    {
        $user = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'Old Name'], $user);

        $action = new UpdateTeamAction;
        $action->execute($team, ['name' => 'New Name', 'logo_path' => 'logos/1.png']);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'New Name',
            'logo_path' => 'logos/1.png',
        ]);
    }

    public function test_can_disband_team(): void
    {
        $user = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'To Disband'], $user);

        $action = new DisbandTeamAction;
        $action->execute($team);

        $this->assertSoftDeleted('teams', [
            'id' => $team->id,
            'status' => 'disbanded',
        ]);
    }

    public function test_can_invite_user(): void
    {
        $captain = User::factory()->create();
        $userToInvite = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'Team A'], $captain);

        $action = new InviteToTeamAction;
        $invitation = $action->execute($team, $userToInvite, $captain);

        $this->assertDatabaseHas('team_invitations', [
            'team_id' => $team->id,
            'invited_user_id' => $userToInvite->id,
            'status' => TeamInvitationStatus::PENDING,
        ]);
    }

    public function test_cannot_invite_existing_member(): void
    {
        $captain = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'Team A'], $captain);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('User is already a member');

        $action = new InviteToTeamAction;
        $action->execute($team, $captain, $captain);
    }

    public function test_can_accept_invitation(): void
    {
        $captain = User::factory()->create();
        $userToInvite = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'Team A'], $captain);

        $invitation = (new InviteToTeamAction)->execute($team, $userToInvite, $captain);

        $action = new AcceptTeamInvitationAction;
        $action->execute($invitation);

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'status' => TeamInvitationStatus::ACCEPTED,
        ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $userToInvite->id,
            'role' => 'member',
        ]);
    }

    public function test_can_decline_invitation(): void
    {
        $captain = User::factory()->create();
        $userToInvite = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'Team A'], $captain);
        $invitation = (new InviteToTeamAction)->execute($team, $userToInvite, $captain);

        $action = new DeclineTeamInvitationAction;
        $action->execute($invitation);

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'status' => TeamInvitationStatus::DECLINED,
        ]);
    }

    public function test_can_revoke_invitation(): void
    {
        $captain = User::factory()->create();
        $userToInvite = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'Team A'], $captain);
        $invitation = (new InviteToTeamAction)->execute($team, $userToInvite, $captain);

        $action = new RevokeTeamInvitationAction;
        $action->execute($invitation);

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'status' => TeamInvitationStatus::REVOKED,
        ]);
    }

    public function test_can_transfer_captaincy(): void
    {
        $captain = User::factory()->create();
        $newCaptain = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'Team A'], $captain);

        $invitation = (new InviteToTeamAction)->execute($team, $newCaptain, $captain);
        (new AcceptTeamInvitationAction)->execute($invitation);

        $action = new TransferTeamCaptainAction;
        $action->execute($team, $newCaptain);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'captain_user_id' => $newCaptain->id,
        ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $captain->id,
            'role' => 'member',
        ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $newCaptain->id,
            'role' => 'captain',
        ]);
    }

    public function test_can_remove_member(): void
    {
        $captain = User::factory()->create();
        $member = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'Team A'], $captain);

        $invitation = (new InviteToTeamAction)->execute($team, $member, $captain);
        (new AcceptTeamInvitationAction)->execute($invitation);

        $action = new RemoveTeamMemberAction;
        $action->execute($team, $member);

        $this->assertDatabaseMissing('team_members', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_expire_invitations_job(): void
    {
        $captain = User::factory()->create();
        $userToInvite = User::factory()->create();
        $team = (new CreateTeamAction)->execute(['name' => 'Team A'], $captain);
        $invitation = (new InviteToTeamAction)->execute($team, $userToInvite, $captain);

        // manually expire
        $invitation->expires_at = now()->subDay();
        $invitation->save();

        (new ExpireTeamInvitationsJob)->handle();

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'status' => TeamInvitationStatus::EXPIRED,
        ]);
    }
}
