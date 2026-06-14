# PlayerSaloons — MVP Progress

**Last Updated**: 2026-06-14 | **Branch**: `main`
**New Admin Features & Compliance Plan**: [PlayerSaloons_New_Admin_Features_Implementation_Plan_v1.md]

---

## ✅ Phase 1 — Migrations & Seeders

**45 migration files** across all domains. All pass `migrate` and `migrate:rollback` cleanly.

| Domain | Tables |
|---|---|
| Identity | `users`, `user_profiles`, `kyc_submissions` |
| Community | `notifications`, `notification_preferences`, `broadcast_messages` |
| Team | `teams`, `team_members`, `team_invitations` |
| CMS | `games`, `game_translations`, `cms_pages`, `cms_page_translations` |
| Tournament | `tournament_templates`, `tournament_template_prizes`, `tournaments`, `tournament_cancellations` (immutable), `tournament_rules`, `tournament_announcements`, `tournament_registrations`, `tournament_participants`, `tournament_checkins` (immutable), `brackets`, `rounds` |
| Match | `matches`, `match_result_submissions`, `match_disputes`, `match_evidence` (immutable) |
| Wallet | `wallets`, `ledger_entries` (immutable), `wallet_transactions`, `deposits`, `withdrawals`, `refunds` (immutable), `prize_distributions` (immutable) |
| Operations | `system_settings`, `job_execution_logs` (immutable) |

**Seeders**: `RolesAndPermissionsSeeder`, `PlatformSystemUserSeeder`, `GamesTableSeeder`, `SystemSettingsSeeder`, `DatabaseSeeder`.

---

## ✅ Phase 2 — Eloquent Models

**32 models** under `app/Modules/*/Models/`. PHPStan Level 8 passing. Key notes:
- **Immutable models** (`LedgerEntry`, `MatchEvidence`, `Refund`, `PrizeDistribution`, `TournamentCancellation`, `TournamentCheckin`, `JobExecutionLog`) — throw `LogicException` on update/delete via `booted()` hooks.
- `GameMatch` maps to the `matches` table to avoid PHP reserved word conflict.
- `User` moved to `app/Modules/Identity/Models/` with custom `newFactory()`.

---

## ✅ Phase 3 — Laravel Enums

**14 backed PHP enums** under `app/Shared/Enums/`:

`TournamentStatus` · `MatchStatus` · `WithdrawalStatus` · `KycStatus` · `TeamInvitationStatus` · `SeatReservationStatus` · `RegistrationStatus` · `PaymentStatus` · `LedgerType` · `DisputeStatus` · `DisputeResolution` · `UserStatus` · `WalletStatus` · `CheckinStatus`

---

## ✅ Phase 4 — State Machines

**7 state machines** under `app/Modules/*/StateMachines/`, extending [`AbstractStateMachine`]
| Machine | Key Transitions | Guards |
|---|---|---|
| `TournamentStateMachine` | DRAFT→PUBLISHED→...→ONGOING→COMPLETED→REFUNDED | Publish config, bracket count, min participants |
| `MatchStateMachine` | PENDING→READY→IN_PROGRESS→COMPLETED; dispute & forfeit paths | — |
| `WalletStateMachine` | ACTIVE↔SUSPENDED↔FROZEN | SUPER_ADMIN check on unfreeze |
| `WithdrawalStateMachine` | PENDING→UNDER_REVIEW→APPROVED→PROCESSED | KYC approved + sufficient balance |
| `KycStateMachine` | NOT_SUBMITTED→SUBMITTED→UNDER_REVIEW→APPROVED/REJECTED | — |
| `InvitationStateMachine` | PENDING→ACCEPTED/DECLINED/EXPIRED/REVOKED | Expiry check |
| `SeatReservationStateMachine` | RESERVED→CONFIRMED/EXPIRED/CANCELLED | — |

**Tests**: `62 unit tests` · 100% passing — [`tests/Unit/StateMachines/`](file:///home/danjay/Projects/playersaloons-app/tests/Unit/StateMachines/)

---

## ✅ Phase 5 — Domain Events

**36 thin domain events** across 7 modules, all extending [`DomainEvent`](file:///home/danjay/Projects/playersaloons-app/app/Shared/Events/DomainEvent.php) (provides `Dispatchable` + immutable `occurredAt`).

| Module | Events |
|---|---|
| Identity | `UserRegistered` `EmailVerified` `UserKycSubmitted` `UserKycApproved` `UserKycRejected` `UserSuspended` `UserUnsuspended` |
| Team | `TeamCreated` `TeamUpdated` `TeamDeleted` `TeamMemberInvited` `TeamMemberJoined` `TeamMemberRemoved` `TeamCaptainChanged` |
| Tournament | `TournamentCreated` `TournamentPublished` `TournamentRegistrationOpened` `TournamentRegistrationClosed` `TournamentCheckinOpened` `TournamentCheckinClosed` `TournamentBracketGenerated` `TournamentStarted` `TournamentCompleted` `TournamentCancelled` `TournamentRefunded` `TournamentSeatReserved` `TournamentSeatReleased` `TournamentFilled` `PlayerCheckedIn` |
| Match | `MatchCreated` `MatchStarted` `MatchResultSubmitted` `MatchResultVerified` `MatchCompleted` `MatchDisputed` `MatchForfeited` |
| Wallet | `WalletCreated` `WalletCredited` `WalletDebited` `EntryFeeCollected` `PrizeAwarded` `RefundIssued` `WithdrawalRequested` `WithdrawalApproved` `WithdrawalRejected` |
| Notification | `NotificationCreated` `NotificationSent` `NotificationFailed` |
| System | `AuditLogCreated` `JobFailed` `SystemMaintenanceStarted` `SystemMaintenanceCompleted` |

All events carry **identifiers only** — no Eloquent model instances. PHPStan Level 8 passing.

---

## ✅ Phase 6 — Identity Module

**Actions, Event Listeners, and Feature Tests** for the Identity domain. PHPStan Level 8 passing.

- **Actions (`app/Modules/Identity/Actions/`)**:
  - `RegisterUserAction`: Atomically registers a user, profile, assigns the `PLAYER` role, and dispatches `UserRegistered`.
  - `SubmitKycAction`: Handles KYC document uploads, validation, state transitioning (`NOT_SUBMITTED` or `REJECTED` -> `SUBMITTED`), and storage.
  - `ReviewKycAction`, `ApproveKycAction`, `RejectKycAction`: Governs the KYC review workflow and roles verification.
  - `SuspendUserAction`, `UnsuspendUserAction`: Manages user status administration.
  - `AssignRoleAction`, `RevokeRoleAction`: Updates user roles in the RBAC system.
  - `UpdateProfileAction`, `UploadAvatarAction`: Manages profile details and avatar uploads.
- **Event Listeners (`app/Modules/Wallet/Listeners/`)**:
  - `CreateWalletListener`: Subscribes to `UserRegistered` and creates a starting active wallet.
- **Tests**:
  - Feature tests added under `tests/Feature/Identity/` (`RegisterUserActionTest` and `SubmitKycActionTest`). 
  - Test suite passing at 100% (68 tests total).

## ✅ Phase 7 — Wallet Service

Ledger entries, transaction processing (credit, debit, lock, unlock), entry fee collection, deposit/withdrawal pipelines.

- **Actions & Services (`app/Modules/Wallet/`)**:
  - `WalletService`: Manages credits, debits, running balances, and recalculations. Enforces state checks (frozen, suspended).
  - Deposit, withdrawal, refund, and prize distributions flows.
- **Tests**:
  - Wallet feature tests passing at 100%.

---

## ✅ Phase 8 — Tournament Module

Templates creation/updating, lifecycle state transitions, registration and check-in flows, bracket and match generation, auto-cancellation, prize calculation and distribution, and async refunding.

- **Actions & Services (`app/Modules/Tournament/`)**:
  - `CreateTournamentTemplateAction`, `UpdateTournamentTemplateAction`, `DeleteTournamentTemplateAction`.
  - `CloseCheckinAction` (marks MISSED check-ins), `CloseRegistrationAction` (calculates tournament prize pool).
  - `ProcessRefundAction` (transitions CANCELLED → REFUNDED).
  - `BracketGenerationService` (single-elimination bracket, rounds, and matches with byes for non-power-of-2 participant counts).
- **Listeners & Jobs (`app/Modules/Tournament/`)**:
  - `AutoCancelTournamentJob` (triggered if checked-in participants < min).
  - `AwardPrizesListener` (calculates distributions, credits winners, handles platform rake and rounding remainder).
  - `IssueRefundsListener` (credits cancelled tournament registrations).
- **Tests**:
  - Full suite of tournament feature tests passing at 100%.

---

## ✅ Phase 9 — Match Module

Match execution, disputes flow, rematch logic, bracket advancement.
- **Actions & Services (`app/Modules/Match/`)**: 
  - `SubmitMatchResultAction`, `ConfirmMatchResultAction`, `ForfeitMatchAction`, `OpenDisputeAction`, `ResolveDisputeAction`. 
- **Listeners & Jobs (`app/Modules/Match/`)**: 
  - `AdvanceWinnerListener` (automates bracket progression), `BroadcastBracketUpdateListener`, `NotifyParticipantsListener`. 
- **Tests**: 
  - Full suite of match feature tests passing at 100%.

--- 

## ✅ Phase 10 — Community & Real-time 

In-app notifications, user preferences, and real-time broadcasting via Reverb/WebSockets. 

- **Actions & Services (`app/Modules/Community/`)**: 
  - `NotificationService`: Handles preferences-aware multi-channel delivery (In-app, Real-time). 
- **Broadcasting Events**: 
  - `BroadcastNotification`, `BroadcastTournamentStarted`, `BroadcastBracketUpdate`, `BroadcastMatchCompleted`. 
- **Listeners & Subscribers**: 
  - `TournamentNotificationListener` (Subscriber): Handles all tournament-related user alerts. 
  - `BroadcastTournamentLifecycleListener`: Manages public real-time bracket and status updates. 
- **Tests**: 
  - Community and notification feature tests passing at 100%.

## ✅ Phase 10 — Team Module

Team creation, management, invitations, captaincy transfers.
- **Actions & Services (`app/Modules/Team/`)**:
  - `CreateTeamAction`, `UpdateTeamAction`, `DisbandTeamAction`.
  - `InviteToTeamAction`, `AcceptTeamInvitationAction`, `DeclineTeamInvitationAction`, `RevokeTeamInvitationAction`.
  - `RemoveTeamMemberAction`, `TransferTeamCaptainAction`.
- **Jobs (`app/Modules/Team/`)**:
  - `ExpireTeamInvitationsJob` (expires unaccepted invitations after `expires_at`).
- **Tests**:
  - Full suite of team feature tests passing at 100%.

## ✅ Phase 11 — Scheduler Automation

- **Jobs (`app/Modules/Tournament/Jobs/`)**:
  - `CloseRegistrationJob`, `OpenCheckinJob`, `CloseCheckinJob`, `StartTournamentJob`, `AutoCancelTournamentJob`, `ExpireReservationsJob`.
  - Configured as sweeping jobs running every minute via `routes/console.php` to perform lifecycle automation tasks.
- **Jobs (`app/Modules/Team/Jobs/`)**:
  - `ExpireTeamInvitationsJob` added to the scheduler to expire pending team invitations.

---

## ✅ Phase 12 — Notifications & Realtime

- **Notification Service (`app/Modules/Community/Services/`)**:
  - `NotificationService`: Manages user notification delivery (in-app DB records, realtime broadcasts, and email dispatch checks) and respects user preference configurations (`NotificationPreference` settings: `email_enabled`, `in_app_enabled`, `realtime_enabled`).
- **Reverb Realtime Broadcast Events (`app/Modules/` and `app/Shared/`)**:
  - `BroadcastNotification` (channel: `user.{uuid}`)
  - `BroadcastTournamentStarted` (channel: `tournament.{uuid}`)
  - `BroadcastTournamentCompleted` (channel: `tournament.{uuid}`)
  - `BroadcastBracketUpdate` (channel: `tournament.{uuid}`)
  - `BroadcastMatchCompleted` (channel: `match.{uuid}`)
- **Key Notification Triggers & Listeners**:
  - `TournamentNotificationListener`: Subscribes to `TournamentSeatReserved` (registration confirmed), `TournamentCheckinOpened` (check-in reminder), `TournamentStarted` (tournament started), and `PrizeAwarded` (prize awarded) events to trigger preference-respecting notifications.
  - `NotifyParticipantsListener`: Updated to notify players on match status changes: match ready (`MatchCreated`), rematch scheduled (`MatchRematchCreated`), match started (`MatchStarted`), match result submitted (`MatchResultSubmitted`), match completed or dispute resolved (`MatchCompleted`), and opponent forfeits (`MatchForfeited`).
  - Wallet: `SendDepositNotificationListener` and `SendNotificationListener` (withdrawal approved/rejected) updated to dispatch preference-aware notifications using the new service.
- **Tests**:
  - Comprehensive feature tests in `tests/Feature/Community/NotificationServiceTest.php` passing 100% (with zero errors across the entire suite of 124 tests).

---

## ✅ Phase 13 — Authorization (RBAC)

- **Modular Policies (`app/Modules/`)**:
  - `TournamentPolicy`: Governs tournament creation, publication, cancellation, and management. Restricts manage/cancel capabilities to the tournament creator for tournament organizers.
  - `MatchPolicy`: Governs match starting, result submissions, and match disputes. Restricts submit and dispute actions to players involved in the match.
  - `WalletPolicy`: Governs wallet viewing, withdrawal requests, and wallet freezing/unfreezing. Enforces that only `SUPER_ADMIN` can unfreeze frozen wallets.
  - `WithdrawalPolicy`: Governs withdrawal review, approval, and rejection. Enforces **Four-Eyes check** (requester cannot self-approve or self-review/reject their own withdrawal request).
  - `KycPolicy`: Governs viewing, reviewing, and approving/rejecting KYC submissions. Allows owner to view their own submission without requiring global view permissions.
  - `TeamPolicy`: Governs team creation, captain management, member invitations, and roster removals. Restricts manage/invite/remove actions strictly to the team captain.
  - `UserPolicy`: Governs user suspension, unsuspension, and role assignment/revocation.
  - `DisputePolicy`: Governs viewing, opening, and resolving disputes. Restricts resolving to organizers/admin and viewing to involved match players.
- **Explicit Registration**:
  - Registered all 8 policies explicitly inside `AppServiceProvider::boot()` using `Gate::policy()` mappings.
- **Tests**:
  - Comprehensive unit and integration test coverage implemented in `tests/Feature/Authorization/PolicyTest.php`.
  - All **139 tests in the project suite are passing successfully** (132 baseline + 7 new API endpoints test suites).

---

## ✅ Phase 14 — API Layer

Exposed `/api/v1` routes with Sanctum auth middleware. Created resources and API controllers utilizing existing module Actions.

- **Endpoints & Controllers (`app/Http/Controllers/Api/V1/`)**:
  - `TournamentApiController`: Exposes public index (paginated, with filters) and show, plus authenticated register and check-in endpoints.
  - `MatchApiController`: Exposes show, result submission (with involved participant checks), and dispute opening.
  - `WalletApiController`: Exposes balance lookup, transaction ledger log listing (paginated), and withdrawal requests (requires KYC approval check).
  - `ProfileApiController`: Exposes show and update profile details. Enforces secure model fields, while returning the referral URL using the plain primary key integer database ID (`?ref=123`) as requested.
  - `TeamApiController`: Exposes team creation, detail retrieval, and inviting new members.
  - `NotificationApiController`: Exposes notification list (paginated) and mark-as-read actions.
- **API Resources (`app/Http/Resources/`)**:
  - `TournamentResource` · `TournamentCollection` · `MatchResource` · `WalletResource` · `LedgerEntryResource` · `UserResource` · `UserProfileResource` · `TeamResource` · `NotificationResource` · `WithdrawalResource`.
  - All resource serialization utilizes `uuid` and hides internal database `id` fields (except user referral URL using raw integer `id`).
- **Authorization & Security**:
  - Injected `Gate` policy checks (e.g. `submitResult`, `dispute`, `requestWithdrawal`, `invite`, `create`) across controllers, returning semantic 403 / 422 JSON error responses.
  - Handled invalid state machine transitions (`InvalidStateTransitionException`) returning 422 errors instead of 500 crashes.
- **Tests**:
  - Comprehensive feature tests implemented in `tests/Feature/Api/ApiEndpointsTest.php` verifying 401 unauthenticated, 403 unauthorized, paginated structures, status filters, and the custom referral URL requirement.
  - 100% passing tests across the entire application suite.

---

## ✅ Phase 15 — Livewire UI (Frontend & Dashboard)

Designed and implemented premium dark neon frontend pages using Tailwind CSS and Livewire 3.

- **Main Layouts (`resources/views/components/layouts/`)**:
  - `app.blade.php`: Public layout with sleek navigation and Lucide icons.
  - `dashboard.blade.php`: High-fidelity sidebar layout with real-time wallet balance display and user status.
- **Public Landing Page (`resources/views/welcome.blade.php`)**:
  - Ultra-modern "Hero" section with glassmorphism effects.
  - Interactive call-to-action buttons for registration and tournament exploration.
  - Marketing stats section showcasing platform reliability.
- **Player Dashboard (`app/Livewire/Dashboard/PlayerDashboard.php` & `resources/views/livewire/dashboard/player-dashboard.blade.php`)**:
  - **Overview**: Real-time view of active matches and registered tournaments.
  - **Head-to-Head (H2H)**: Integrated matchmaking interface with stake selection and simulated opponent matching.
  - **Global Chat**: Interactive mock chat system with auto-replies to simulate platform activity.
  - **Tournaments**: Compact views for browsing and managing current registrations.
- **Profile Dashboard (`app/Livewire/Profile/ProfileDashboard.php` & `resources/views/livewire/profile/profile-dashboard.blade.php`)**:
  - Tabular settings for changing display name, bio, country, and timezone.
  - Custom referral URL field displaying the raw database primary key integer ID (`?ref=123`).
  - Document upload form supporting ID cards, passports, and driver's licenses with live KYC status badge.
  - Direct DB update toggles for Email, In-App, and Real-Time notification preferences.
- **Team Dashboard (`app/Livewire/Team/TeamDashboard.php` & `resources/views/livewire/team/team-dashboard.blade.php`)**:
  - Handles incoming invites and team creation.
  - Inside a team: Roster listing with captaincy badge.
  - Captain actions: invite new players, revoke outbound invites, remove team members, transfer captaincy to another member, rename team, and disband the team.
  - Member actions: leave the team.
- **Mobile Responsiveness**:
  - Tournament bracket list uses horizontal overflow with scroll snap (`snap-x flex-nowrap`) to ensure fluid scrolling on mobile devices.
  - UI styled with clean spacing, readable typography (Inter and Orbitron), custom status badges, and Lucide icons.
- **UI Polishing (v1.1)**:
  - Refined the Player Dashboard interface for better aesthetics and mobile usability.
  - Updated the topbar title from **SYSTEM DASHBOARD** to **DASHBOARD**.
  - Optimized topbar for mobile by hiding the title while keeping essential action icons (Deposit, Notifications, Profile, and Language Switcher) visible.
  - Enlarged the platform logo in the sidebar for stronger brand presence and removed the **PLAYERSALOONS** text branding for a modern "icon-first" design.
- **Tournament Discovery & Dashboard Enhancements (v1.2)**:
  - Added `frequency` (Daily/Weekly/Monthly) and `banner_url` to tournaments for better visual presentation.
  - Enhanced `TournamentList` with a frequency filter and rich card design featuring image banners.
  - Refactored `PlayerDashboard` with a tabbed interface ("My Tournaments & Stats" vs "Browse & Register").
  - Implemented real-time tournament filtering (Search, Game, Status, Frequency) directly within the player dashboard.
  - Added past tournament history section and reusable `tournament-card-item` component for consistency.
- **Routing (`routes/web.php`)**:
  - Standardized web routing under `guest` and `auth` middleware groups.
- **Tests**:
  - Run and passed the entire test suite (139 tests, 100% passing).

---

## ✅ Phase 16 — Admin Panel (Livewire)

Full-featured internal operations dashboard for staff (ADMIN / SUPER_ADMIN roles). Built on Livewire 3 with a dedicated admin layout at `resources/views/components/layouts/admin.blade.php`.

- **Base Class (`app/Livewire/Admin/AdminComponent.php`)**:
  - Abstract base enforcing staff-only access via `boot()` — redirects with 403 if the authenticated user lacks the `ADMIN` or `SUPER_ADMIN` role.

- **Admin Layout (`resources/views/components/layouts/admin.blade.php`)**:
  - Dark professional theme (Slate-950 / Slate-900 surfaces).
  - Responsive sidebar with Lucide icons, mobile burger menu, and live user name/role display.
  - Flash message toast system (success / error / info).

- **Admin Dashboard (`AdminDashboard` → `/admin`)**:
  - Live stats grid: total users, pending KYC, pending withdrawals, active tournaments, ongoing matches, open disputes, platform escrow balance.
  - Recent activity feeds for KYC and withdrawals with quick status badges.

- **Tournament Admin (`TournamentAdmin` → `/admin/tournaments`)**:
  - Searchable, filterable paginated tournament list.
  - Create/Edit modal for draft tournaments (full date/time fields, game selector, fee, participant limits).
  - State-transition buttons (`applyTransition`) covering the full lifecycle: Publish → Open Registration → Close Registration → Open Check-in → Close Check-in → Generate Bracket → Start → Complete → Process Refund.
  - Cancel modal with mandatory reason and audit note.

- **Match Admin (`MatchAdmin` → `/admin/matches`)**:
  - Searchable match list with dispute filter (active disputes highlighted).
  - Result override panel: select winner, write override notes, trigger `MatchStateMachine` override.
  - Dispute resolution panel: view evidence, choose resolution (`PLAYER_A_WINS`, `PLAYER_B_WINS`, `DRAW`, `REMATCH`), resolve via `ResolveDisputeAction`.

- **KYC Admin (`KycAdmin` → `/admin/kyc`)**:
  - Status-filtered KYC queue (SUBMITTED / UNDER_REVIEW / APPROVED / REJECTED).
  - Side-panel detail view with document links, submitted data.
  - One-click Approve or Reject (with mandatory rejection reason note).

- **Withdrawal Admin (`WithdrawalAdmin` → `/admin/withdrawals`)**:
  - Defaults to `PENDING` status filter with search by username / email.
  - Selecting a withdrawal auto-moves it to `UNDER_REVIEW` (four-eyes guard: reviewer ≠ requester).
  - Approve modal (with notes) and Reject modal (mandatory reason).
  - Process Payout button for `APPROVED` withdrawals.
  - Shows linked KYC status and last 10 wallet ledger entries inline.

- **User Admin (`UserAdmin` → `/admin/users`)**:
  - Paginated user list with status and role filters.
  - Detail panel: suspend / unsuspend action with reason, role assignment / revocation (all non-SUPER_ADMIN roles), view wallet balance and KYC status.

- **Audit Log Admin (`AuditLogAdmin` → `/admin/audit-logs`)**:
  - Date-range, actor, action-type, and entity-type filters.
  - Paginated log table showing actor, action, entity, and timestamp.

- **CMS Admin (`CmsAdmin` → `/admin/cms`)**:
  - Tabbed interface: **Games** tab and **Pages** tab.
  - Games: toggle active/inactive, edit English translations (name / description).
  - Pages: list all CMS pages with locale, status badge; publish action.

- **Routing (`routes/web.php`)**:
  - All admin routes mounted under `/admin` prefix inside the `auth` middleware group:
    - `/admin` · `/admin/tournaments` · `/admin/matches` · `/admin/kyc` · `/admin/withdrawals` · `/admin/users` · `/admin/audit-logs` · `/admin/cms`

- **UI Polish & Role-Based UI Separation (v1.1)**:
  - Implemented dynamic role-based login redirects: players redirect to `/dashboard`, staff/admin roles redirect to `/admin`.
  - Added request-time checks in `PlayerDashboard::mount()` to auto-redirect staff users accessing `/dashboard` to `/admin`.
  - Added "Admin Panel" sidebar and profile dropdown links to `/dashboard` for staff members to easily switch views.
  - Enhanced the admin layout header (desktop & mobile) to show logged-in staff username, dynamic role labels, and dedicated color-coded shields (e.g. red for `SUPER_ADMIN`, indigo for `ADMIN`).
  - Added a sign-out button directly inside the admin header.

- **Bug Fixes**:
  - Renamed `TournamentAdmin::transition()` → `applyTransition()` to avoid conflict with Livewire's reserved `transition()` lifecycle method.
  - Fixed `WithdrawalAdmin::reject()` union type hint (removed erroneous `RejectKycAction` from union).
  - Fixed `TournamentAdmin` validation: replaced `max:max_participants` (invalid cross-field ref) with `lte:max_participants`.
  - Added `rounds()` `hasManyThrough` relationship to `Tournament` model (via `Bracket`).

- **UI Refinements & Fixes (v1.3)**:
  - **Compact Admin Profile Icon**: Refined the admin header layout, making the user display name and role badge more compact and adjusting margins to prevent overlapping.
  - **Action Icons Fix (Global)**: Fixed the bug where clicking action buttons caused the icons (e.g. eye, edit, delete, cancel) to disappear and turn into empty circles. Implemented global Livewire `livewire:init` + `morph.updated` + `message.processed` hooks inside all layout files to auto-re-initialize Lucide icons upon any DOM morphing or request processing.
  - **Livewire DOM Keying (`wire:key`)**: Added unique `wire:key` attributes to table rows (`<tr>`) inside all admin views (`tournament-admin`, `match-admin`, `audit-log-admin`, `cms-admin`, `kyc-admin`, `user-admin`, `withdrawal-admin`) to ensure proper Livewire DOM tracking.
  - **Custom Dark-Neon Pagination**: Styled the default Laravel/Livewire pagination component in `app.css` using custom CSS overrides. The pagination now blends seamlessly with the dark-neon theme (deep slate background, indigo-to-violet gradient for active pages, subtle glow effects).
  - **Tournament Pagination Spacing**: Wrapped the tournament admin pagination in an `mt-6` container for improved visual spacing.

- **Tournament Admin Refactor & CMS Improvements (v1.4)**:
  - Extracted Tournament creation/editing from a modal into a dedicated page (`/admin/tournaments/create` and `/admin/tournaments/{id}/edit`) for better UX and spacious layout.
  - Implemented Livewire's `wire:navigate` for SPA-like lazy loaded transitions between the tournament list and form.
  - Expanded tournament configuration fields (prize pools, team size, checkin/registration timings, etc.) and added an automatic default rules template generator.
  - Added dynamic **Platform Management** to the CMS Admin (`/admin/cms`). Platforms are now database-driven (`platforms` table), configurable via CRUD actions, and automatically populate the platform select dropdown when creating tournaments.
  - Improved CMS UI by replacing browser alerts with a polished custom delete confirmation modal for both CMS Pages and Platforms.

- **Advanced Tournament Features & Performance (v1.5)**:
  - **Modal Optimization**: Integrated Alpine.js for instant modal visibility and backdrop control. Optimized server-side `render()` logic to prevent unnecessary relationship loading when modals are closed. Added `wire:loading` states and skeletons.
  - **Reusable Action Dropdown**: Created a reusable `x-admin.action-dropdown` Blade component for consistent 'kebab' action menus across all admin tables.
  - **Limited Edit Mode**: Implemented "Limited Edit" (Option A) for tournaments, allowing admins to update rules, descriptions, and schedules for published tournaments while locking critical financial/structural fields.
  - **Multi-Step Tournament Wizard**: Refactored the tournament creation/edit form into a 4-step interactive wizard (Identity, Settings, Schedule, Prizes) with strict per-step validation and real-time button disabling.
  - **Rich Text Integration**: Integrated **Quill Rich Text Editor** for tournament descriptions and rules with optimized deferred synchronization to eliminate typing lag.
  - **Expanded Filters & Schedules**: Added "One-time / Single Event" frequency option. Made Platform selection mandatory and implemented Platform/Frequency filters on both Admin and Player tournament lists.
  - **Draft Persistence**: Implemented local persistence using `localStorage` to automatically save tournament drafts, preventing data loss during creation.
  - **Scheduling Guidance**: Added instructional helper notes to Step 3 of the wizard to guide admins on chronological date requirements (Registration < Check-in < Start).
  - **Dynamic Status Badges**: Implemented color-coded status badges for tournament list.
    - *Technical Debt Note*: Currently uses inline styles to bypass CSS compilation issues in the environment. Future refactor needed to revert to standard Tailwind utility classes using a dedicated blade component once build pipeline is stabilized.

- **Tournament Access & Visibility Improvements (v1.7)**:
  - **Player Restriction (Logic)**: Updated `TournamentDetail` to enforce 'PLAYER' role requirement for registration in the backend.
  - **Player Restriction (UI)**: Updated `tournament-detail.blade.php` to hide the 'Join' button and show an informative message for non-player roles.
  - **Tournament Visibility**: Refactored `TournamentList` to filter out inactive statuses (Draft, Completed, Cancelled, Refunded) by default, showing only active tournaments (Registration, Check-in, Ongoing).

- **Tournament Modularization & Routing Fixes (v1.10)**:
  - **Modular Tournament Components**: Refactored tournament listing into a shared `TournamentListTrait` to maintain consistency across public and player-context components (`PublicTournamentList` and `PlayerTournamentList`) while ensuring layout/theme isolation.
  - **Route Separation**: Explicitly separated public (`/tournaments`) and authenticated player (`/tournaments/browse`) routes, fixing 404 and layout issues for guest users.

---

## ⚠️ Pending Test Coverage

- **Tournament Admin Features (v1.6-1.11)**:
  - Need to add feature tests for:
    - Admin Tournament Filter Persistence (`TournamentAdmin` component).
    - Frequency tabs functionality in admin and player contexts.
    - Custom pagination component rendering.
    - Role-based restriction on the 'Join Tournament' button.
    - Tournament status filtering on player-side listing.
  - Need to add component tests for:
    - `MyTournamentsList` (active/history tabs).
    - `PlayerTournamentList` (Browse Tournaments filtering).
    - `HeadToHeadList` (matchmaking simulation, challenge creation).
  - Need to add integration/E2E tests for:
    - Navigation between dashboard, my-tournaments, and browse-tournaments pages.
