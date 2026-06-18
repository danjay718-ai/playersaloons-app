# User Flow: Team Management

This document describes the social and competitive grouping features for players.

## 1. Create Team
Founding a new team for group competitions.

*   **Action**: Player fills out the team creation form.
*   **UI Component**: `app/Livewire/Team/TeamDashboard.php`
*   **View**: `resources/views/livewire/team/team-dashboard.blade.php`
*   **Logic (Actions)**:
    *   `app/Modules/Team/Actions/CreateTeamAction.php`: Creates the team and assigns the creator as captain.
*   **Connected Files**:
    *   `app/Modules/Team/Models/Team.php`: The primary team model.
    *   `app/Modules/Team/Models/TeamMember.php`: Pivot model tracking membership and roles (e.g., CAPTAIN).
    *   `app/Modules/Team/Events/TeamCreated.php`.

## 2. Invite Member
Growing the team by adding other players.

*   **Action**: Captain searches for a player and sends an invitation.
*   **UI Component**: `app/Livewire/Team/TeamDashboard.php`
*   **Logic (Actions)**:
    *   `app/Modules/Team/Actions/InviteToTeamAction.php`: Creates a pending invitation.
*   **Connected Files**:
    *   `app/Modules/Team/Models/TeamInvitation.php`: Model for tracking sent invites.
    *   `app/Modules/Team/StateMachines/InvitationStateMachine.php`: Governs invite lifecycle (PENDING -> ACCEPTED/DECLINED/EXPIRED).
    *   `app/Modules/Team/Events/TeamMemberInvited.php`.

## 3. Accept/Decline Invite
Players responding to team invitations.

*   **Action**: Player views their pending invites and chooses to join or decline.
*   **UI Component**: `app/Livewire/Team/TeamDashboard.php`
*   **Logic (Actions)**:
    *   `app/Modules/Team/Actions/AcceptTeamInvitationAction.php`: Transitions invitation to ACCEPTED and creates a `TeamMember` record.
    *   `app/Modules/Team/Actions/DeclineTeamInvitationAction.php`: Transitions invitation to DECLINED.
*   **Connected Files**:
    *   `app/Modules/Team/Events/TeamMemberJoined.php`.

## 4. Manage Roster
Captain-specific controls for the team.

*   **Action**: Captain removes members or transfers captaincy.
*   **UI Component**: `app/Livewire/Team/TeamDashboard.php`
*   **Logic (Actions)**:
    *   `app/Modules/Team/Actions/RemoveTeamMemberAction.php`: Removes a player from the team.
    *   `app/Modules/Team/Actions/TransferTeamCaptainAction.php`: Changes the team captain.
    *   `app/Modules/Team/Actions/DisbandTeamAction.php`: Deletes the team and removes all members.
*   **Connected Files**:
    *   `app/Modules/Team/Policies/TeamPolicy.php`: Restricts management actions to the captain.
    *   `app/Modules/Team/Jobs/ExpireTeamInvitationsJob.php`: Automatically cleans up stale invites via the scheduler.

## 🧪 Isolated Test Cases
### 1. Membership & Permissions
*   **Success**: `test_can_invite_user`
    *   Assert `team_invitations` table has entry with status `PENDING`.
*   **Error**: `test_cannot_invite_existing_member`
    *   Assert `LogicException` is thrown when inviting an existing member.
*   **Success**: `test_can_accept_invitation`
    *   Assert `team_members` table has entry.
    *   Assert invitation status is `ACCEPTED`.
*   **Success**: `test_can_decline_invitation`
    *   Assert invitation status is `DECLINED`.
*   **Success**: `test_can_revoke_invitation`
    *   Assert invitation status is `REVOKED`.

### 2. Team Lifecycle
*   **Success**: `test_can_create_team`
    *   Assert `teams` entry exists with correct captain.
    *   Assert `team_members` entry exists with role `captain`.
*   **Success**: `test_can_update_team`
    *   Assert `teams` entry has updated name and logo_path.
*   **Success**: `test_can_disband_team`
    *   Assert `teams` entry is soft-deleted with status `disbanded`.

### 3. Roster Management
*   **Success**: `test_can_transfer_captaincy`
    *   Assert `teams.captain_user_id` updated.
    *   Assert old captain's role is `member`, new captain's role is `captain`.
*   **Success**: `test_can_remove_member`
    *   Assert `team_members` entry is deleted.

### 4. Scheduler
*   **Success**: `test_expire_invitations_job`
    *   Assert expired invitations are transitioned to `EXPIRED` status.

## ✅ PHPStan (v1.27)
0 errors at Level 5. Fixed `$fillable` PHPDoc on `Team`, `TeamMember`, and `TeamInvitation` from `array<int, string>` → `list<string>` to satisfy the covariant override constraint on `Illuminate\Database\Eloquent\Model::$fillable`.

## 🛠️ Feature Gaps & Unused Schema
*   **Missing Features**:
    *   **Team Leveling/XP**: Schema for `teams.level` or `teams.xp` is missing; no progression logic implemented.
    *   **Team Tournaments**: Current registration flow only handles solo players.
*   **Unused Schema Columns**:
    *   `teams.logo_path`: Field exists but `CreateTeamAction` doesn't handle image uploads yet.
    *   `team_members.role`: Currently defaults to `MEMBER` or `CAPTAIN`, but potential for `CO_CAPTAIN` or `MODERATOR` exists in the schema.
