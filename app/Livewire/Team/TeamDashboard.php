<?php

declare(strict_types=1);

namespace App\Livewire\Team;

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
use App\Modules\Team\Models\Team;
use App\Modules\Team\Models\TeamInvitation;
use App\Modules\Team\Models\TeamMember;
use App\Shared\Enums\TeamInvitationStatus;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TeamDashboard extends Component
{
    // Creating Team
    public string $teamName = '';

    // Editing Team
    public string $editName = '';

    // Inviting Members
    public string $inviteUsername = '';

    public function mount(): void
    {
        $user = Auth::user();
        if ($user) {
            $adminRoles = ['SUPER_ADMIN', 'ADMIN', 'MODERATOR', 'FINANCE_OPERATOR', 'KYC_REVIEWER', 'SUPPORT_AGENT', 'TOURNAMENT_ORGANIZER'];
            if ($user->hasAnyRole($adminRoles)) {
                $this->redirect('/admin');
            }
        }
    }

    public function createTeam(CreateTeamAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            $this->redirect('/login');

            return;
        }

        $this->validate([
            'teamName' => ['required', 'string', 'min:3', 'max:50', 'unique:teams,name'],
        ]);

        try {
            $action->execute(['name' => $this->teamName], $user);
            session()->flash('message', 'Team created successfully!');
            $this->reset('teamName');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function updateTeam(UpdateTeamAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $this->getCurrentTeam();

        if (! $user || ! $team || $team->captain_user_id !== $user->id) {
            session()->flash('error', 'Only the captain can edit team details.');

            return;
        }

        $this->validate([
            'editName' => ['required', 'string', 'min:3', 'max:50', 'unique:teams,name,'.$team->id],
        ]);

        try {
            $action->execute($team, ['name' => $this->editName]);
            session()->flash('message', 'Team updated successfully!');
            $this->reset('editName');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function disbandTeam(DisbandTeamAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $this->getCurrentTeam();

        if (! $user || ! $team || $team->captain_user_id !== $user->id) {
            session()->flash('error', 'Only the captain can disband the team.');

            return;
        }

        try {
            $action->execute($team);
            session()->flash('message', 'Team disbanded successfully!');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function inviteMember(InviteToTeamAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $this->getCurrentTeam();

        if (! $user || ! $team || $team->captain_user_id !== $user->id) {
            session()->flash('error', 'Only the captain can invite members.');

            return;
        }

        $this->validate([
            'inviteUsername' => ['required', 'string', 'exists:users,username'],
        ]);

        try {
            $invitedUser = User::query()->where('username', $this->inviteUsername)->firstOrFail();
            $action->execute($team, $invitedUser, $user);
            session()->flash('message', "Invitation sent to {$invitedUser->username}!");
            $this->reset('inviteUsername');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function revokeInvitation(string $invitationUuid, RevokeTeamInvitationAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $this->getCurrentTeam();

        if (! $user || ! $team || $team->captain_user_id !== $user->id) {
            session()->flash('error', 'Only the captain can revoke invitations.');

            return;
        }

        try {
            $invitation = TeamInvitation::query()->where('uuid', $invitationUuid)->firstOrFail();
            $action->execute($invitation);
            session()->flash('message', 'Invitation revoked.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function acceptInvitation(string $invitationUuid, AcceptTeamInvitationAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            $invitation = TeamInvitation::query()
                ->where('uuid', $invitationUuid)
                ->where('invited_user_id', $user->id)
                ->firstOrFail();

            // Check if user is already in a team
            $existingMember = TeamMember::query()->where('user_id', $user->id)->first();
            if ($existingMember) {
                session()->flash('error', 'You must leave your current team first.');

                return;
            }

            $action->execute($invitation);
            session()->flash('message', 'Invitation accepted! Welcome to the team.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function declineInvitation(string $invitationUuid, DeclineTeamInvitationAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            $invitation = TeamInvitation::query()
                ->where('uuid', $invitationUuid)
                ->where('invited_user_id', $user->id)
                ->firstOrFail();

            $action->execute($invitation);
            session()->flash('message', 'Invitation declined.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function removeMember(string $username, RemoveTeamMemberAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $this->getCurrentTeam();

        if (! $user || ! $team || $team->captain_user_id !== $user->id) {
            session()->flash('error', 'Only the captain can remove members.');

            return;
        }

        try {
            $memberUser = User::query()->where('username', $username)->firstOrFail();
            $action->execute($team, $memberUser);
            session()->flash('message', "{$memberUser->username} was removed from the team.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function leaveTeam(RemoveTeamMemberAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $this->getCurrentTeam();

        if (! $user || ! $team) {
            return;
        }

        if ($team->captain_user_id === $user->id) {
            session()->flash('error', 'As captain, you must disband the team or transfer captaincy first.');

            return;
        }

        try {
            $action->execute($team, $user);
            session()->flash('message', 'You have left the team.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function transferCaptaincy(string $username, TransferTeamCaptainAction $action): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $this->getCurrentTeam();

        if (! $user || ! $team || $team->captain_user_id !== $user->id) {
            session()->flash('error', 'Only the captain can transfer captaincy.');

            return;
        }

        try {
            $newCaptain = User::query()->where('username', $username)->firstOrFail();
            $action->execute($team, $newCaptain);
            session()->flash('message', "Captaincy transferred to {$newCaptain->username}.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function getCurrentTeam(): ?Team
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return TeamMember::query()
            ->where('user_id', $user->id)
            ->first()
            ?->team;
    }

    /**
     * @param  View|Factory  $view
     * @return mixed
     */
    private function resolveView($view)
    {
        return $view;
    }

    /**
     * @return mixed
     */
    public function render()
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            $this->redirect('/login');

            return view('livewire.team.team-dashboard', [
                'team' => null,
                'myPendingInvites' => collect(),
                'teamMembers' => collect(),
                'teamPendingInvites' => collect(),
            ]);
        }

        $team = $this->getCurrentTeam();

        // Load pending invites received by user
        $myPendingInvites = TeamInvitation::query()
            ->where('invited_user_id', $user->id)
            ->where('status', TeamInvitationStatus::PENDING)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['team', 'inviter'])
            ->get();

        // Load details for current team if user belongs to one
        $teamMembers = collect();
        $teamPendingInvites = collect();

        if ($team) {
            $teamMembers = $team->members()->with('user.profile')->get();
            $teamPendingInvites = $team->invitations()
                ->where('status', TeamInvitationStatus::PENDING)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->with('invitee')
                ->get();

            if (! $this->editName) {
                $this->editName = $team->name;
            }
        }

        $view = view('livewire.team.team-dashboard', [
            'team' => $team,
            'myPendingInvites' => $myPendingInvites,
            'teamMembers' => $teamMembers,
            'teamPendingInvites' => $teamPendingInvites,
        ]);

        return $this->resolveView($view)->layout('components.layouts.app', ['title' => 'My Team | PlayerSaloons']);
    }
}
