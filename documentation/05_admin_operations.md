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
    *   `app/Modules/Wallet/Actions/ProcessWithdrawalAction.php`: Confirms the external payout was sent and marks the withdrawal as PROCESSED. Same role guard.
    *   `app/Modules/Wallet/Listeners/CreateLedgerEntryListener.php`: Debits the wallet asynchronously when `WithdrawalApproved` is dispatched.
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
    *   `app/Modules/Match/Actions/ResolveHeadToHeadDisputeAction.php`: Resolves H2H disputes by awarding creator, awarding opponent, or voiding/refunding both locked stakes.
    *   `app/Modules/Match/Actions/ForfeitMatchAction.php`: Manually forfeits a match for a player.
*   **Connected Files**:
    *   `app/Modules/Match/Models/MatchDispute.php`: Tracks dispute status.
    *   `app/Modules/Match/Models/HeadToHeadMatch.php`: Stores H2H result proof, dispute notes/proof, and admin resolution metadata.
    *   `app/Modules/Match/Policies/MatchPolicy.php`: Grants admins override permissions.
    *   `app/Modules/Match/Listeners/AdvanceWinnerListener.php`: Re-triggered after dispute resolution to progress the bracket.

## 5. User Moderation
Managing player accounts and roles.

*   **Action**: Admin suspends a user or updates their roles.
*   **UI Component**: `app/Livewire/Admin/UserAdmin.php`
*   **Logic (Actions)**:
    *   `app/Modules/Identity/Actions/SuspendUserAction.php`: Disables account access. Enforces `ADMIN | SUPER_ADMIN`.
    *   `app/Modules/Identity/Actions/AssignRoleAction.php`: Updates RBAC roles. **Restricted to `SUPER_ADMIN` only** (not plain ADMIN).
*   **Online Presence**: Each row in the user table shows a dot indicator — emerald if online (active in last 5 min via `User::isOnline()` / Redis), slate if offline.
*   **Connected Files**:
    *   `app/Modules/Identity/Policies/UserPolicy.php`: Restricts moderation to staff.
    *   `app/Modules/Identity/Events/UserSuspended.php`.
    *   `app/Http/Middleware/UpdateUserOnlineStatus.php`: Sets `user_online:{id}` Redis key (TTL 300s) on every authenticated web request.

## 6. Broadcast Notifications
Managing platform-wide broadcast messages.

*   **Route**: `/admin/notifications`
*   **UI Component**: `app/Livewire/Admin/BroadcastNotificationAdmin.php`
*   **Blade Partials** (reusable via `@include`):
    *   `livewire/admin/partials/broadcast/_table.blade.php`
    *   `livewire/admin/partials/broadcast/_form-modal.blade.php`
    *   `livewire/admin/partials/broadcast/_confirm-modal.blade.php`
*   **Actions by role**:
    *   ADMIN / SUPER_ADMIN: Create, Edit, Expire (sets `ends_at = now()`)
    *   SUPER_ADMIN only: Delete (permanent)
*   **Status logic**: Active (within window), Scheduled (starts_at in future), Expired (ends_at in past).

## 7. Staff Activity Dashboard
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

## 8. CMS & Landing Page Management
Managing public content, game catalog labels, and the editable landing page.

*   **Route**: `/admin/cms`
*   **UI Component**: `app/Livewire/Admin/CmsAdmin.php`
*   **Navigation Tables**:
    *   `public_navigation_items`: Stores editable public navbar links, visibility rules, icons, active match patterns, sort order, and status.
*   **Landing Tables**:
    *   `landing_sections`: Stores section-level content such as hero title/body/video, section headings, CTA, sort order, and visibility.
    *   `landing_section_items`: Stores cards/steps/stat labels/features/reviews/footer links for each section.
*   **Landing Public Component**: `app/Livewire/Landing/LandingPage.php`
*   **Data Service**: `app/Modules/CMS/Services/LandingPageContentService.php`
*   **Editable Landing Areas**:
    *   Hero copy, CTA, and video path (`/compressed_v1.mp4` by default).
    *   How-it-works steps.
    *   Stat card labels/icons/order; values are computed live, not manually entered.
    *   Feature cards.
    *   Review cards.
    *   Footer copy and links.
*   **Game Catalog Landing Fields**:
    *   `games.banner_path`: Optional image path used by the landing game carousel.
    *   `game_translations.description`: Editable game description shown on landing game cards.
*   **Public Navigation Areas**:
    *   Desktop nav links come from active `public_navigation_items`.
    *   Mobile burger menu shows the same nav items plus the install action; guest Sign In / Join Now remain visible in the mobile topbar.
    *   Visibility supports everyone, guests only, signed-in users, players, staff, and guests-or-players.
*   **Database-driven Areas**:
    *   Games list comes from active rows in `games` and `game_translations`, displayed as a horizontal carousel.
    *   Live stats are computed from matches, H2H matches, prize/H2H payout ledger entries, active users, and active games.
    *   Top players of the week are computed from completed tournament matches and weekly prize activity.
*   **Defaults**: `database/seeders/LandingPageSeeder.php` creates the standard landing sections/items for fresh installs.

## 9. Policy Pages
Managing public legal/policy pages outside the generic CMS page system.

*   **Route**: `/admin/policies`
*   **UI Component**: `app/Livewire/Admin/PolicyAdmin.php`
*   **Public Routes**:
    *   `/policies`: Index of active, published policies.
    *   `/policies/{slug}`: Detail view for one active, published policy.
*   **Policy Table**:
    *   `policy_pages`: Stores slug, title, summary, content, sort order, active state, published timestamp, and last updater.
*   **Editor**: Body content uses the same Quill rich text editor pattern as the tournament wizard, storing formatted HTML for public rendering.
*   **Seeded Policy Pages**:
    *   Terms and Conditions (`/policies/terms-and-conditions`)
    *   Cookie Policy (`/policies/cookie-policy`)
    *   Privacy Policy (`/policies/privacy-policy`)
    *   Refund and Cancellation Policy (`/policies/refund-and-cancellation-policy`)
    *   Disclaimer (`/policies/disclaimer`)
*   **Reason for Separate Area**: Policy pages are operational/legal content and are intentionally separate from `cms_pages`, so they can have fixed expected slugs, dedicated admin UX, and predictable public footer links.

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
*   **Landing CMS**: `test_admin_can_update_landing_section_and_create_item`
    *   Assert admin can update section content and add landing cards/items.
*   **Navigation CMS**: `test_admin_can_manage_public_navigation_items`
    *   Assert admin can create and toggle public navbar items.
*   **Dynamic Landing Render**: `test_landing_page_renders_seeded_content_video_and_games`
    *   Assert `/` renders seeded content, the video path, game catalog cards, and dynamic sections.
*   **Policy Pages**: `CMS/PolicyPageTest`
    *   Assert guests can view seeded policy pages, inactive/unpublished policies 404, admins can edit policy content, and players cannot access `/admin/policies`.

### 📋 Pending Tests (Testing Debt)
*   `test_admin_tournament_filter_persistence`: Ensure search/status filters remain set after page refresh.
*   `test_custom_pagination_rendering`: Verify the dark-neon styled pagination component renders correctly.
*   `test_admin_frequency_tab_functionality`: Verify the tournament admin list filters correctly by Daily/Weekly/Monthly.
*   `test_admin_navigation_flow`: Verify `wire:navigate` SPA-like transitions between list and create/edit pages.

## 🛠️ Feature Gaps & Unused Schema
*   **Missing Features**:
    *   None currently tracked in this document.
*   **Unused Schema Columns**:
    *   `system_settings.category`: Settings are currently a flat list; no categorization UI.
    *   `job_execution_logs.performance_metrics`: JSON field for tracking job speed/memory usage currently empty.
