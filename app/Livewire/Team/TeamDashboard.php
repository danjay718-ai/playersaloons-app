<?php

declare(strict_types=1);

namespace App\Livewire\Team;

use App\Livewire\Concerns\HandlesUserFacingErrors;
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
use App\Modules\Team\Models\TeamJoinRequest;
use App\Modules\Team\Models\TeamMember;
use App\Modules\Tournament\Models\Tournament;
use App\Shared\Enums\TeamInvitationStatus;
use App\Shared\Enums\TournamentStatus;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class TeamDashboard extends Component
{
    use HandlesUserFacingErrors;

    // Creating Team
    public string $teamName = '';

    // Editing Team
    public string $editName = '';

    // Inviting Members
    public string $inviteUsername = '';

    public string $squadSearch = '';

    public string $joinRequestMessage = '';

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
            session()->flash('error', $this->safeError($e, 'Unable to create the team.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to update the team.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to delete the team.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to invite that player.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to accept the invitation.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to decline the invitation.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to remove the team member.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to change the team captain.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to leave the team.'));
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
            session()->flash('error', $this->safeError($e, 'Unable to update the team member.'));
        }
    }

    public function requestToJoin(int $teamId): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        if (TeamMember::query()->where('user_id', $user->id)->where('status', 'active')->exists()) {
            session()->flash('error', 'Leave your current squad before requesting another one.');

            return;
        }

        $this->validate(['joinRequestMessage' => 'nullable|string|max:500']);
        $team = Team::query()->where('status', 'active')->findOrFail($teamId);

        TeamJoinRequest::query()->updateOrCreate([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ], [
            'uuid' => Str::uuid()->toString(),
            'message' => trim($this->joinRequestMessage) ?: null,
        ]);

        $this->reset('joinRequestMessage');
        session()->flash('message', "Join request sent to {$team->name}.");
    }

    public function reviewJoinRequest(string $uuid, bool $approve): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $this->getCurrentTeam();
        $manager = $team?->members()->where('user_id', $user?->id)->whereIn('role', ['captain', 'co_captain'])->exists() ?? false;
        if (! $user || ! $team || ! $manager) {
            abort(403);
        }

        DB::transaction(function () use ($uuid, $approve, $user, $team): void {
            $request = TeamJoinRequest::query()
                ->where('uuid', $uuid)
                ->where('team_id', $team->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->firstOrFail();

            if ($approve && TeamMember::query()->where('user_id', $request->user_id)->where('status', 'active')->exists()) {
                throw new \LogicException('This player has already joined another squad.');
            }

            if ($approve) {
                TeamMember::query()->updateOrCreate([
                    'team_id' => $team->id,
                    'user_id' => $request->user_id,
                ], [
                    'role' => 'member',
                    'status' => 'active',
                    'joined_at' => now(),
                ]);
            }

            $request->update([
                'status' => $approve ? 'approved' : 'declined',
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);
        });

        session()->flash('message', $approve ? 'Player added to the squad.' : 'Join request declined.');
    }

    public function updateMemberRole(int $memberId, string $role): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $team = $this->getCurrentTeam();
        if (! $user || ! $team || (int) $team->captain_user_id !== (int) $user->id) {
            abort(403);
        }
        if (! in_array($role, ['co_captain', 'member'], true)) {
            throw new \InvalidArgumentException('Invalid squad role.');
        }

        $member = $team->members()->whereKey($memberId)->where('user_id', '!=', $user->id)->firstOrFail();
        $member->update(['role' => $role]);
        session()->flash('message', 'Squad role updated.');
    }

    private function getCurrentTeam(): ?Team
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        return TeamMember::query()
            ->with('team')
            ->where('user_id', $user->id)
            ->where('status', 'active')
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
                'teamJoinRequests' => collect(),
                'squadDirectory' => collect(),
                'teamFinderTournaments' => collect(),
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
        $teamJoinRequests = collect();

        if ($team) {
            $teamMembers = $team->members()->with('user.profile')->get();
            $teamPendingInvites = $team->invitations()
                ->where('status', TeamInvitationStatus::PENDING)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->with('invitee')
                ->get();
            $isManager = $team->members()->where('user_id', $user->id)->whereIn('role', ['captain', 'co_captain'])->exists();
            if ($isManager) {
                $teamJoinRequests = $team->joinRequests()->where('status', 'pending')->with('user.profile')->oldest()->get();
            }

            if (! $this->editName) {
                $this->editName = $team->name;
            }
        }

        $squadDirectory = $team === null
            ? Team::query()->where('status', 'active')
                ->when(trim($this->squadSearch) !== '', fn ($query) => $query->where('name', 'like', '%'.trim($this->squadSearch).'%'))
                ->withCount(['members' => fn ($query) => $query->where('status', 'active')])
                ->orderByDesc('members_count')->orderBy('name')->limit(20)->get()
            : collect();

        // Keep the finder lightweight: only load open team tournaments and the
        // relations required by the compact discovery cards.
        $teamFinderTournaments = Tournament::query()
            ->select([
                'id', 'uuid', 'name', 'game_id', 'platform_id', 'team_size',
                'max_participants', 'registration_close_at',
            ])
            ->where('status', TournamentStatus::REGISTRATION_OPEN)
            ->where('team_size', '>', 1)
            ->where(function ($query): void {
                $query->whereNull('registration_close_at')
                    ->orWhere('registration_close_at', '>', now());
            })
            ->with([
                'game:id,slug',
                'game.translations:id,game_id,locale,name',
                'platform:id,name',
            ])
            ->withCount('registrations')
            ->orderBy('registration_close_at')
            ->limit(8)
            ->get();

        $view = view('livewire.team.team-dashboard', [
            'team' => $team,
            'myPendingInvites' => $myPendingInvites,
            'teamMembers' => $teamMembers,
            'teamPendingInvites' => $teamPendingInvites,
            'teamJoinRequests' => $teamJoinRequests,
            'squadDirectory' => $squadDirectory,
            'teamFinderTournaments' => $teamFinderTournaments,
        ]);

        return $this->resolveView($view)->layout('components.layouts.dashboard', [
            'title' => 'Squads & Teams | PlayerSaloons',
            'dashboard_title' => 'SQUADS & TEAMS',
        ]);
    }
}
