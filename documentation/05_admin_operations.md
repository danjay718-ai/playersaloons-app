# User Flow: Admin & Operations

This document details the operational workflows for staff members (ADMIN / SUPER_ADMIN).

## 1. KYC Review
Approving or rejecting player identity submissions.

*   **Action**: Admin reviews submitted documents and updates the status.
*   **UI Component**: `app/Livewire/Admin/KycAdmin.php`
*   **View**: `resources/views/livewire/admin/kyc-admin.blade.php`
*   **Logic (Actions)**:
    *   `app/Modules/Identity/Actions/ApproveKycAction.php`: Marks KYC as approved. Enforces `KYC_REVIEWER | ADMIN | SUPER_ADMIN` role.
    *   `app/Modules/Identity/Actions/RejectKycAction.php`: Marks KYC as rejected with a reason. Same role guard.
*   **Security**: `KycAdmin` calls `$reviewer->can('approve'/'reject', $submission)` (via `KycPolicy`) before executing actions.
*   **Connected Files**:
    *   `app/Modules/Identity/StateMachines/KycStateMachine.php`: Governs the review lifecycle.
    *   `app/Modules/Identity/Policies/KycPolicy.php`: Restricts review actions to staff.
    *   `app/Modules/Identity/Events/UserKycApproved.php`.

## 2. Withdrawal Approval
Verifying and processing cash-out requests.

*   **Action**: Admin reviews a pending withdrawal and approves it for payout.
*   **UI Component**: `app/Livewire/Admin/WithdrawalAdmin.php`
*   **Logic (Actions)**:
    *   `app/Modules/Wallet/Actions/ApproveWithdrawalAction.php`: Moves request to APPROVED status. Enforces `FINANCE_OPERATOR | ADMIN | SUPER_ADMIN` role and four-eyes check.
    *   `app/Modules/Wallet/Actions/ProcessWithdrawalAction.php`: Executes the final debit and marks as PROCESSED. Same role guard.
*   **Security**: `WithdrawalAdmin::approve()` and `processPayout()` both call `$reviewer->can('approve', $withdrawal)` (via `WithdrawalPolicy`) before executing actions.
*   **Connected Files**:
    *   `app/Modules/Wallet/Policies/WithdrawalPolicy.php`: Enforces the **Four-Eyes Principle** (reviewer cannot be the requester).
    *   `app/Modules/Wallet/StateMachines/WithdrawalStateMachine.php`: Manages the multi-step approval flow.
    *   `app/Modules/Wallet/Events/WithdrawalApproved.php`.

## 3. Tournament Control
Managing the lifecycle of tournaments.

*   **Action**: Admin publishes, cancels, or manually progresses a tournament.
*   **UI Component**: `app/Livewire/Admin/TournamentAdmin.php` (list + lifecycle transitions)
*   **Create/Edit Component**: `app/Livewire/Admin/TournamentForm.php` (multi-step creation form at `/admin/tournaments/create`)
*   **Logic (Actions)**:
    *   `app/Modules/Tournament/Actions/CreateTournamentAction.php`: Initializes a new tournament.
    *   `app/Modules/Tournament/Actions/CancelTournamentAction.php`: Triggers the cancellation and refund flow.
*   **Connected Files**:
    *   `app/Modules/Tournament/StateMachines/TournamentStateMachine.php`: The core engine for tournament states.
    *   `app/Modules/Tournament/Actions/ProcessRefundAction.php`: Automates refunds for cancelled tournaments.
    *   `app/Modules/Tournament/Jobs/AutoCancelTournamentJob.php`: Triggered if participation is too low.

## 4. Match & Dispute Management
Resolving conflicts and overriding results.

*   **Action**: Admin reviews dispute evidence and makes a final ruling.
*   **UI Component**: `app/Livewire/Admin/MatchAdmin.php`
*   **Logic (Actions)**:
    *   `app/Modules/Match/Actions/ResolveDisputeAction.php`: Finalizes a dispute with a chosen outcome (e.g., PLAYER_A_WINS). Accepts `User $actor` and enforces `ADMIN | SUPER_ADMIN` role check.
    *   `app/Modules/Match/Actions/ForfeitMatchAction.php`: Manually forfeits a match for a player.
*   **Connected Files**:
    *   `app/Modules/Match/Models/MatchDispute.php`: Tracks dispute status.
    *   `app/Modules/Match/Policies/MatchPolicy.php`: Grants admins override permissions.
    *   `app/Modules/Match/Listeners/AdvanceWinnerListener.php`: Re-triggered after dispute resolution to progress the bracket.

## 5. User Moderation
Managing player accounts and roles.

*   **Action**: Admin suspends a user or updates their roles.
*   **UI Component**: `app/Livewire/Admin/UserAdmin.php`
*   **Logic (Actions)**:
    *   `app/Modules/Identity/Actions/SuspendUserAction.php`: Disables account access. Enforces `ADMIN | SUPER_ADMIN`.
    *   `app/Modules/Identity/Actions/AssignRoleAction.php`: Updates RBAC roles. **Restricted to `SUPER_ADMIN` only** (not plain ADMIN).
*   **Connected Files**:
    *   `app/Modules/Identity/Policies/UserPolicy.php`: Restricts moderation to staff.
    *   `app/Modules/Identity/Events/UserSuspended.php`.

## 6. Staff Activity Dashboard
Monitoring admin actions across the platform.

*   **Action**: Super-admin views a per-staff breakdown of all logged actions for a date range.
*   **Route**: `/admin/staff-activity`
*   **UI Component**: `app/Livewire/Admin/StaffActivityDashboard.php`
*   **View**: `resources/views/livewire/admin/staff-activity-dashboard.blade.php`
*   **Access**: `ADMIN` and `SUPER_ADMIN` only (additional role check in `boot()`).
*   **Features**:
    *   Filter by staff username and date range (defaults to last 7 days).
    *   Per-staff action count breakdown (e.g., "kyc_approved ×5, withdrawal_approved ×2").
    *   Top-10 actions summary across all staff in the period.

## 🧪 Isolated Test Cases
### 1. Security & Guards
*   **Role Protection**: `test_non_admin_cannot_access_admin_dashboard` / `test_player_cannot_access_staff_activity_dashboard`
    *   Assert redirect to login or 403.
*   **Audit Logging**: `test_admin_action_is_logged_in_activity_log`
    *   Perform an approval/rejection.
    *   Assert `activity_log` has entry with `causer_id` as the admin.

### 2. Operational Workflows
*   **KYC Approval**: `test_kyc_admin_can_approve_kyc`
    *   Assert `kyc_submissions.status` updates to `APPROVED`.
*   **Match Override**: `test_match_admin_can_override_result`
    *   Manually set winner for an ongoing match.
    *   Assert `status = COMPLETED` and `winner_registration_id` set.
*   **Tournament Create**: `test_tournament_admin_can_create_tournament`
    *   Uses `TournamentForm` component (not `TournamentAdmin`).
    *   Assert `tournaments` row with `status = DRAFT`.
*   **Staff Activity**: `test_admin_can_access_staff_activity_dashboard`, `test_staff_activity_dashboard_shows_staff_members`, `test_staff_activity_dashboard_filters_by_date`, `test_staff_activity_dashboard_filters_by_staff_name`

### 📋 Pending Tests (Testing Debt)
*   `test_admin_tournament_filter_persistence`: Ensure search/status filters remain set after page refresh.
*   `test_custom_pagination_rendering`: Verify the dark-neon styled pagination component renders correctly.
*   `test_admin_frequency_tab_functionality`: Verify the tournament admin list filters correctly by Daily/Weekly/Monthly.
*   `test_admin_navigation_flow`: Verify `wire:navigate` SPA-like transitions between list and create/edit pages.

## 🛠️ Feature Gaps & Unused Schema
*   **Missing Features**:
    *   **Mass Notifications**: UI for sending broadcast messages to all users (schema for `broadcast_messages` exists).
*   **Unused Schema Columns**:
    *   `system_settings.category`: Settings are currently a flat list; no categorization UI.
    *   `job_execution_logs.performance_metrics`: JSON field for tracking job speed/memory usage currently empty.
