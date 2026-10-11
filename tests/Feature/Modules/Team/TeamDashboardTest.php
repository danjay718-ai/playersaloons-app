<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Team;

use App\Livewire\Notification\NotificationBell;
use App\Livewire\Team\TeamDashboard;
use App\Modules\Identity\Models\User;
use App\Modules\Team\Actions\AcceptTeamInvitationAction;
use App\Modules\Team\Actions\CreateTeamAction;
use App\Modules\Team\Actions\InviteToTeamAction;
use App\Modules\Team\Actions\RemoveTeamMemberAction;
use App\Modules\Team\Models\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TeamDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_captain_can_rename_the_squad_and_disband_it_from_settings(): void
    {
        $captain = User::factory()->create();
        $team = app(CreateTeamAction::class)->execute(['name' => 'Original Squad'], $captain);

        Livewire::actingAs($captain)->test(TeamDashboard::class)
            ->set('editName', 'Renamed Squad')->call('updateTeam')
            ->assertHasNoErrors()->assertSee('Renamed Squad')
            ->call('disbandTeam')->assertSee('Create Squad');

        $this->assertSoftDeleted('teams', ['id' => $team->id, 'name' => 'Renamed Squad']);
        $this->assertDatabaseMissing('team_members', ['team_id' => $team->id]);
    }

    public function test_player_search_invitation_and_notification_reach_the_recipient(): void
    {
        $captain = User::factory()->create();
        $recipient = User::factory()->create(['username' => 'searchable_player']);
        $recipient->profile()->create(['uuid' => (string) Str::uuid(), 'display_name' => 'Ace Gamer']);
        $team = app(CreateTeamAction::class)->execute(['name' => 'Inviting Squad'], $captain);

        Livewire::actingAs($captain)->test(TeamDashboard::class)
            ->set('inviteUsername', 'Ace Gamer')->assertSee('searchable_player')
            ->assertDontSee($recipient->email)
            ->set('inviteUsername', $recipient->username)->call('inviteMember')
            ->assertHasNoErrors()->assertSee('Invitation sent to '.$recipient->username);

        $invitation = TeamInvitation::query()->sole();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id, 'type' => 'squad_invitation', 'action_url' => '/teams',
        ]);
        Livewire::actingAs($recipient)->test(NotificationBell::class)->assertSee('Squad invitation');
        Livewire::actingAs($recipient)->test(TeamDashboard::class)
            ->assertSee('Inviting Squad')->assertSee($captain->username)
            ->call('acceptInvitation', $invitation->uuid)->assertHasNoErrors()
            ->assertSee('SQUAD MEMBERS');
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $recipient->id]);
    }

    public function test_member_can_leave_without_changing_other_memberships(): void
    {
        $captain = User::factory()->create();
        $member = User::factory()->create();
        $team = app(CreateTeamAction::class)->execute(['name' => 'Leave Squad'], $captain);
        $invite = app(InviteToTeamAction::class)->execute($team, $member, $captain);
        app(AcceptTeamInvitationAction::class)->execute($invite);

        Livewire::actingAs($member)->test(TeamDashboard::class)
            ->call('leaveTeam')->assertHasNoErrors()->assertSee('You have left the team.')
            ->assertSee('Create Squad');
        $this->assertDatabaseMissing('team_members', ['team_id' => $team->id, 'user_id' => $member->id]);
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $captain->id]);
    }

    public function test_duplicate_invitation_has_a_readable_inline_error(): void
    {
        $captain = User::factory()->create();
        $recipient = User::factory()->create();
        $team = app(CreateTeamAction::class)->execute(['name' => 'Duplicate Squad'], $captain);
        app(InviteToTeamAction::class)->execute($team, $recipient, $captain);

        Livewire::actingAs($captain)->test(TeamDashboard::class)
            ->set('inviteUsername', $recipient->username)->call('inviteMember')
            ->assertHasErrors('inviteUsername')
            ->assertSee('This player already has a pending invitation to your squad.')
            ->assertDontSee('SQLSTATE')->assertDontSee('LogicException');
        $this->assertDatabaseCount('team_invitations', 1);
    }

    public function test_invitation_is_visible_even_when_the_player_is_in_another_squad(): void
    {
        $captain = User::factory()->create();
        $recipient = User::factory()->create();
        app(CreateTeamAction::class)->execute(['name' => 'Current Squad'], $recipient);
        $team = app(CreateTeamAction::class)->execute(['name' => 'Another Squad'], $captain);
        $invite = app(InviteToTeamAction::class)->execute($team, $recipient, $captain);

        Livewire::actingAs($recipient)->test(TeamDashboard::class)
            ->assertSee('Current Squad')->assertSee('Another Squad')->assertSee('PENDING INVITATIONS')
            ->call('acceptInvitation', $invite->uuid)->assertSee('You must leave your current team first.');
        $this->assertSame('pending', $invite->fresh()->status->value);
    }

    public function test_unexpected_leave_failure_is_readable_and_preserves_membership(): void
    {
        $captain = User::factory()->create();
        $member = User::factory()->create();
        $team = app(CreateTeamAction::class)->execute(['name' => 'Safe Errors Squad'], $captain);
        app(AcceptTeamInvitationAction::class)->execute(app(InviteToTeamAction::class)->execute($team, $member, $captain));
        $action = Mockery::mock(RemoveTeamMemberAction::class);
        $action->shouldReceive('execute')->once()->andThrow(new RuntimeException('SQLSTATE private failure details'));
        $this->app->instance(RemoveTeamMemberAction::class, $action);

        Livewire::actingAs($member)->test(TeamDashboard::class)->call('leaveTeam')
            ->assertSee('Unable to leave the team.')->assertDontSee('SQLSTATE');
        $this->assertDatabaseHas('team_members', ['team_id' => $team->id, 'user_id' => $member->id]);
    }

    public function test_non_captain_cannot_rename_or_invite_members(): void
    {
        $captain = User::factory()->create();
        $member = User::factory()->create();
        $team = app(CreateTeamAction::class)->execute(['name' => 'Protected Squad'], $captain);
        app(AcceptTeamInvitationAction::class)->execute(app(InviteToTeamAction::class)->execute($team, $member, $captain));

        Livewire::actingAs($member)->test(TeamDashboard::class)
            ->set('editName', 'Unauthorized Rename')->call('updateTeam')
            ->assertSee('Only the captain can edit team details.')
            ->call('inviteMember')->assertSee('Only the captain can invite members.');
        $this->assertSame('Protected Squad', $team->fresh()->name);
    }
}
