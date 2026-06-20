# PlayerSaloons — MVP Progress

**Last Updated**: 2026-06-20 (v1.37) | **Branch**: `main`

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
  - `BracketGenerationService` (single-elimination bracket, rounds, and matches with byes for non-power-of-2 participant counts). **Refactored (v1.23)**: Added `.values()` for safe 0-based Collection indexing, renamed vars for clarity, extracted `nextPowerOfTwo()` private method, fixed bye-slot seeding to use `Collection::get()` instead of unsafe `[]` operator.
- **Listeners & Jobs (`app/Modules/Tournament/`)**:
  - `AutoCancelTournamentJob` (triggered if checked-in participants < min).
  - `AwardPrizesListener` (calculates distributions, credits winners, handles platform rake and rounding remainder).
  - `IssueRefundsListener` (credits cancelled tournament registrations).
- **Tests**:
  - Full suite of tournament feature tests passing at 100%.
  - Unit tests for `BracketGenerationService` covering 2, 5, 6, and 8 player bracket sizes with byes mathematics.

---

## ✅ Phase 9 — Match Module

Match execution, disputes flow, rematch logic, bracket advancement.
- **Actions & Services (`app/Modules/Match/`)**: 
  - `SubmitMatchResultAction`, `ConfirmMatchResultAction`, `ForfeitMatchAction`, `OpenDisputeAction`, `ResolveDisputeAction`. 
- **Listeners & Jobs (`app/Modules/Match/`)**: 
  - `AdvanceWinnerListener` (automates bracket progression), `BroadcastBracketUpdateListener`, `NotifyParticipantsListener`. 
- **Tests**: 
  - Full suite of match feature tests passing at 100%.
  - Feature tests for the complete `confirmResult` -> `MatchCompleted` -> `AdvanceWinnerListener` flow and `AutoForfeitJob` timeout.

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
  - `TournamentPolicy`: Governs tournament creation, publication, cancellation, management, and restricted details view. Restricts viewing players, matches, and activity tabs to registered participants or organizers/admins via `viewRestrictedDetails` policy.
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

- **Tournament Lifecycle & Admin Control Improvements (v1.14)**:
  - **State Rollbacks**: Implemented valid state transition rollbacks in `TournamentStateMachine` (e.g., re-opening check-in from closed status), allowing for better tournament management flexibility.
  - **Check-in Validation Guard**: Added a mandatory participant count validation (`guardCanCloseCheckin`) to prevent premature check-in closure if tournament participation requirements are not met.
  - **Admin UI Enhancements**: Exposed new rollback transitions ('Re-open Registration', 'Re-open Check-in') directly in the Admin Tournament action dropdown for immediate workflow adjustment.
  - **Bug Fix**: Added missing `checkins()` relationship to the `Tournament` model to support participation validation logic.

- **Head-to-Head Feature Modularization (v1.11)**:
  - **Dedicated H2H Page**: Extracted 'Head-to-Head' duels from the `PlayerDashboard` into a dedicated Livewire page (`/head-to-head`), improving load times and simplifying the dashboard DOM.
  - **Prototype/Mock Implementation**: Current H2H functionality uses mock in-memory data for UI demonstration. Production implementation (backend tables, matchmaking engine, websockets) is tracked in [PlayerSaloons_Execution_Checklist_v1.md].
  - **Navigation Update**: Updated sidebar to include a direct link to the new H2H page and removed H2H tab from the main dashboard.

- **Dashboard Redesign (v1.13)**:
  - **Cockpit Overview**: Refactored the `PlayerDashboard` to act as a lightweight 'Cockpit' overview. It now features widgets for user welcome/balance, recent matches, upcoming tournaments, progression stats, announcements, and a video placeholder.
  - **Tabbed Navigation**: Re-implemented tabbed navigation within the dashboard to allow quick switching between the Cockpit view and the new dedicated pages (My Tournaments, Browse Tournaments, H2H, Leaderboards, Streams, Chat).

- **Match Resolution Automation (v1.16)**:
  - **Match Startup Automation**: Implemented `AutoStartMatchesListener` and updated `AdvanceWinnerListener` to automatically transition 'READY' matches to 'IN_PROGRESS' when a tournament starts or a player advances, enabling immediate action for participants.
  - **Backfill Command**: Created `tournaments:start-matches` Artisan command to manually fix matches stuck in 'READY' status for ongoing tournaments.
  - **AutoForfeitJob Registration**: Registered `AutoForfeitJob` in the Laravel scheduler to run every minute.
  - **Infrastructure Requirement**: Production deployment requires a configured cron job (`php artisan schedule:run`) and a persistent queue worker (`php artisan queue:work`) supervised process.
  - **Testing Coverage (v1.23)**: Implemented `ConfirmResultFlowTest` validating successful opponent confirmation, winner advancement/progression, and `AutoForfeitJob` timeout resolution.

- **Tournament UI Enhancements (v1.17)**:
  - **Persistent Tabs**: Implemented `localStorage` state persistence for tournament content tabs using Alpine.js, scoped per tournament ID.
  - **Activity Feed Tab**: Added a new 'Activity' tab featuring a vertical timeline layout to display chronological tournament events (`$tournament->activities`).
  - **Mobile Layout & Usability**: Re-designed the navigation tab container to use horizontal scrolling on mobile devices to prevent wrapping breakages, and applied `whitespace-nowrap`.
  - **Simplified Terminology**: Renamed default tabs from 'Intel/Warriors/Battle Grid' to clearer 'Overview/Players/Matches'.

- **Tournament Elimination Warning Modal (v1.18)**:
  - **Elimination Verification**: Calculates `$hasLost` status inside `TournamentDetail` by checking if the logged-in player has a resolved match (`COMPLETED` or `FORFEITED`) in the tournament where they are not the winner.
  - **Alpine.js Warning Modal**: Shows a custom modal with a skull icon notifying players that they are eliminated when selecting the Matches tab.
  - **Navigation Flow**: Provides a "Go Back" option (reverts tab to Overview) and a "Continue" option (allows them to proceed viewing matches).

- **My Tournaments UI Redesign (v1.19)**:
  - **Player Statistics Banner**: Added a top row statistics grid showing Active Tournaments, Tournament History, Match Victories (Wins), and Match Defeats (Losses).
  - **Active Tab Alignment**: Re-designed active tournament cards to match the neon styling, banner images, status badges, and fee/prize layouts of the Browse Tournaments page.
  - **History List View**: Re-engineered the history tab as a detailed chronological list item view displaying game category, end date, and user match histories (including round number, opponent name, and custom outcome badges like "WON" or "LOST").
  - **Elimination Lifecycle Shift**: Tournaments where a player has lost are immediately moved from the "Active" tab to the "History" tab, and the stats banner counts reflect this database-driven status.

- **Performance Optimizations (v1.20)**:
  - **Eager Loading Counts**: Implemented `withCount` on active tournament registrations for both browse and player listings, replacing inline loop queries with optimized database subqueries (`registrations_count`).
  - **Pre-fetched Matches**: Replaced loop-level N+1 query structures on `/my-tournaments` page by pre-fetching all player matches for paginated tournaments in a single DB query, reducing page load queries drastically.
  - **Eager Activity Logs**: Swapped inline database loops for Spatie activity logs in `player-content.blade.php` with the pre-fetched `$activityLogs` collection passed from the component.

- **File Storage Fix & Restriction (v1.21)**:
  - **S3/R2 Removed Locally**: Switched dispute evidence (`SubmitEvidenceAction`) and match result proof (`SubmitMatchResultAction`) uploads from the `r2` disk to the local `public` disk, resolving the `Class "League\Flysystem\AwsS3V3\PortableVisibilityConverter" not found` error.
  - **Images Only, 2MB Cap**: Restricted allowed file types to images only (PNG, JPG, WEBP) and capped size at 2MB for both upload flows. Removed PDF and video (MP4/MOV) support for now.
  - **Validation Sync**: Updated Livewire `MatchDetail` component validation for both `evidenceFile` and `submissionProof` to reflect the new limits (`max:2048`, `mimes:png,jpg,jpeg,webp`).
  - **UI Copy Updated**: Updated hint text and dispute description in `match-detail.blade.php` to reflect the new restrictions.
  - **Storage URL Compatibility**: Existing blade templates already used `/storage/{{ $path }}` for both fields — confirmed correct for the `public` disk with no additional changes.
  - **Deployment Note**: Added `🚀 Deployment Considerations` section to this file with a step-by-step checklist to migrate back to R2/S3 before going live.

- **Match Result Confirmation Fixes (v1.23)**:
  - **`confirmResult` Stub Fixed**: The `MatchDetail::confirmResult()` Livewire method was a no-op stub (`// ... (existing method)`). Implemented the full body: load match with relations, auth guard, delegate to `ConfirmMatchResultAction`, and flash messages.
  - **`MatchCompleted::dispatch()` TypeError Fixed**: `ConfirmMatchResultAction::execute()` was passing a `GameMatch` object to `MatchCompleted::dispatch()` but the event constructor expects `(int $matchId, int $tournamentId, int $winnerRegistrationId)`. Fixed by extracting `winner_registration_id` from the latest submission, persisting it on the match, and dispatching with correct `int` arguments.
  - **`BracketGenerationService`**: Fixed unsafe Eloquent Collection index access (`$participants[(int)]` → `$participants->get(int)`) by adding `.values()` after `orderBy()`. Renamed `$p` → `$bracketSize` for clarity. Extracted `nextPowerOfTwo()` as a private method. Documented that `TournamentParticipant` rows are exclusively checked-in players (created by `CheckinParticipantAction`), so no additional filter is needed.
  - **`Prize Pool Retention`**: Fixed a bug where a manually set or guaranteed prize pool in the tournament wizard was overwritten with `0.00` when registration closed (or when completing a tournament). Updated `CloseRegistrationAction` and `PrizeCalculationService` to retain the higher of the manually entered prize pool and the calculated registration fees (minus platform rake).
  - **Testing Coverage (v1.23)**: Implemented unit tests for `BracketGenerationService` covering 2, 5, 6, and 8 player tournament bracket generation structures (byes math & propagation). Implemented feature tests for the complete `confirmResult` → `MatchCompleted` → `AdvanceWinnerListener` flow and the `AutoForfeitJob` timeout mechanism.

- **Admin Dispute Evidence UI (v1.22)**:
  - **Detail Modal — Dispute Section Redesign**: Replaced the plain file-link list with a full dispute card per dispute showing: filed-by user with timestamp, status badge (open/under_review/resolved with distinct colours), player's note/reason in a labelled block, and a 2-column image thumbnail grid (clickable to open full image in new tab) with hover overlay showing uploader name. Fallback for broken image links included.
  - **Resolve CTA Scope Expanded**: The "Resolve Dispute" button now appears for both `open` and `under_review` dispute statuses (previously only `open`).
  - **Dispute Resolution Modal Upgraded**: Widened from `max-w-md` to `max-w-2xl`, made scrollable (`max-h-[90vh]`). Now eager-loads `openedBy` and `evidence.uploadedBy` relations. Shows: filed-by header with status badge, player note block, evidence image thumbnail grid (with zoom-in hover overlay), then the admin ruling radio buttons (with `has-[:checked]` highlight styles for emerald/amber). Submit button renamed to "Submit Ruling" with a gavel icon.
  - **No backend changes required**: All data was already available via existing relations; only the Blade template was updated.


- **Tournament Admin Features (v1.6-1.11)**:
  - Need to add feature tests for:
    - Admin Tournament Filter Persistence (`TournamentAdmin` component).
    - Frequency tabs functionality in admin and player contexts.
    - Custom pagination component rendering.
    - Role-based restriction on the 'Join Tournament' button.
    - Tournament status filtering on player-side listing.
    - Restricted details view (`viewRestrictedDetails` policy) limiting Players, Matches, and Activity tabs visibility to registered participants, organizers, and admins.
  - Need to add component tests for:
    - `TournamentDetail` elimination modal:
      - Player has lost -> navigating to Matches tab triggers warning modal.
      - Player has not lost -> navigating to Matches tab does not trigger modal.
      - Clicking "Go Back" in modal resets active tab to "Overview".
      - Clicking "Continue" in modal closes the modal and keeps active tab on "Matches".
    - `MyTournamentsList` logic and styling:
      - Calculating stats banner counts (active, history, wins, losses) directly from DB aggregates.
      - Active tab matching Browse page cards.
      - History tab presenting lists of user match details (opponent, round, win/loss badge).
      - Player elimination shifting tournaments from the Active list to the History list.
      - N+1 query check (ensuring matches and registration counts are retrieved in grouped queries rather than nested loops).
    - `PlayerTournamentList` (Browse Tournaments filtering).
    - `HeadToHeadList` (matchmaking simulation, challenge creation).
  - Need to add integration/E2E tests for:
    - Navigation between dashboard, my-tournaments, and browse-tournaments pages.

---

## ✅ PHPStan Fixes (v1.25)

Resolved 5 PHPStan Level 8 errors across Match, Wallet, and Tournament modules.

- **`GameMatch` model**: Added missing `@property Carbon|null $result_submitted_at` to PHPDoc. The column was already cast to `datetime` but not declared, causing a `string|null` type mismatch when assigning `Carbon::now()` in `SubmitMatchResultAction`.
- **`Wallet` model**: Added `@property WalletStatus $status` PHPDoc. Without it, PHPStan inferred the property as `string` (from the `$fillable` array), making strict enum comparisons (`=== WalletStatus::FROZEN/SUSPENDED`) in `WalletService` always evaluate to false.
- **`TournamentModuleTest`**: Fixed `AutoCancelTournamentJob::dispatchSync($tournament->id)` → `dispatchSync()`. The job has no constructor and queries tournaments internally; passing an argument violated the Larastan `larastan.jobs.noConstructor` rule.

---

## 🚀 Deployment Considerations

### File Storage — Switch Local → R2/S3

Currently, all user-uploaded files (dispute evidence screenshots and match result proof images) are stored on the **local `public` disk** (`storage/app/public/`). This works for local development but is **not suitable for production** (files won't persist across deployments and won't scale).

**Before going live:**
1. Install the required adapter: `composer require league/flysystem-aws-s3-v3`.
2. Set the correct `.env` variables for Cloudflare R2 (or AWS S3):
   - `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_URL`
3. In `SubmitEvidenceAction::execute()` — change disk from `'public'` → `'r2'`.
4. In `SubmitMatchResultAction::execute()` — change disk from `'public'` → `'r2'`.
5. Update any file URL helpers (currently `/storage/{{ $path }}`) to use `Storage::disk('r2')->url($path)` so URLs resolve correctly from R2/CDN.
6. Run `php artisan storage:link` if keeping any local public files on the server.

> **Note:** File type restriction is currently **images only** (PNG, JPG, WEBP, max 2MB). If video evidence is needed in production, update `SubmitEvidenceAction::ALLOWED_MIME_TYPES` and the Livewire validation in `MatchDetail.php` accordingly.

---

## ✅ Financial Operations Audit & Fixes (v1.26)

Audited the full financial operations flow against `documentation/03_financial_operations.md`. Fixed source bugs, resolved PHPStan errors, aligned all tests to the correct architecture.

- **`ProcessWithdrawalAction`**: Removed erroneous `WalletService::debit()` call. Debit is handled by `CreateLedgerEntryListener` on `WithdrawalApproved` (async/queued). Added missing role guard (`FINANCE_OPERATOR / ADMIN / SUPER_ADMIN`). Added `processed_at` stamp.
- **`CreateLedgerEntryListener`**: Fixed idempotency on `WithdrawalApproved`. Old guard (`status !== APPROVED`) was insufficient — on queue retry the status is still `APPROVED`, causing double debit. Now checks for existing `LedgerEntry` with matching `reference_type + reference_id` before debiting.
- **`MatchStateMachine`**: Fixed a historical result-submission mismatch. This note was superseded in v1.37 when `WAITING_FOR_CONFIRMATION` became the canonical post-submission state and `RESULT_SUBMITTED` was retained only for legacy compatibility.
- **`TournamentStateMachine`**: Added missing `COMPLETED → REFUNDED` and `CANCELLED → REFUNDED` transitions (`REFUNDED` existed in the enum but not in the transition table). Extracted `activity()` call into a `protected logTransition()` method so unit tests can override it without hitting the `activity_log` DB table.
- **`Withdrawal` model**: Added `@property WithdrawalStatus $status` PHPDoc. Added `processed_at` to `$fillable` and `casts`. Fixed `$fillable` PHPDoc to `list<string>`. Fixed `BelongsTo` return type PHPDoc to `BelongsTo<T, $this>`.
- **`Deposit` model**: Added `fee_amount` to `$fillable` and `casts`. Fixed `$fillable` PHPDoc. Fixed `BelongsTo` return type PHPDoc.
- **`KycSubmission` model**: Added `@property KycStatus $status` PHPDoc. Fixed `$fillable` PHPDoc to `list<string>`.
- **`WithdrawalStateMachine`**: Added null-safety guards for `$kyc` and `$wallet` before calling approval guards.
- **Docblock fixes**: Removed duplicate `float` in `@param string|float|float $amount` in `ProcessDepositAction` and `RequestWithdrawalAction`.
- **`TournamentStateMachineTest`**: Switched to anonymous subclass overriding `logTransition()` to suppress `activity_log` DB calls in unit tests.
- **Tests**: 88 tests passing (40 wallet feature + 48 state machine unit). Added 7 new test cases covering deposit idempotency, reject withdrawal, process withdrawal role guard, process withdrawal status/timestamp, `CreateLedgerEntryListener` debit, listener idempotency, and ledger sum = cached balance.
- **PHPStan**: 0 errors on all modified files at Level 5.

---

## ✅ Team Module Fixes & Convention Alignment (v1.27)

Audited Team module against `documentation/04_team_management.md`. Fixed PHPStan errors, applied missing conventions, wired unused components, and aligned docs to reality.

- **Models (`Team`, `TeamMember`, `TeamInvitation`)**: Added `declare(strict_types=1)`. Fixed `$fillable` PHPDoc from `array<int, string>` → `list<string>` to satisfy covariant override constraint (PHPStan Level 5, 3 errors resolved).
- **`InvitationStateMachine`**: Was completely unused — actions were directly mutating `$invitation->status`. Wired into `AcceptTeamInvitationAction`, `DeclineTeamInvitationAction`, and `RevokeTeamInvitationAction` via constructor injection.
- **Event Dispatch**: All 7 team events existed but were never dispatched. Fixed by emitting the correct event from each action: `TeamCreated`, `TeamUpdated`, `TeamDeleted`, `TeamMemberInvited`, `TeamMemberJoined`, `TeamMemberRemoved`, `TeamCaptainChanged`. Added `User $actingUser` param to `UpdateTeamAction`, `DisbandTeamAction`, and `RemoveTeamMemberAction` to carry actor ID into events.
- **`04_team_management.md`**: Corrected stale test names (`test_captain_can_invite_player` → `test_can_invite_user`, etc.), added all 11 test cases across 4 sections, fixed `teams.logo_url` → `teams.logo_path`.
- **Tests**: Updated test file to use `app()` for DI-injected actions and pass the required `User` actor arg. All **11 team tests passing**.

---

## ✅ Phase 6 — Identity Module Fixes & Test Coverage Audit (v1.24)

Audited the full Identity & Onboarding flow against `documentation/01_identity_onboarding.md`. Fixed source bugs, aligned tests to the doc-specified names and assertions, and removed dead code.

- **`RegisterUserAction`**: Moved `UserRegistered::dispatch()` outside `DB::transaction()`. Previously the event could be picked up by a queued listener before the transaction committed, causing a missing-row race condition.
- **`SubmitKycAction`**: Replaced `(new KycSubmission)->newQuery()` with `KycSubmission::query()`. Removed unreachable `$path === false` guard (file is already validated before `store()` is called).
- **`CreateWalletListener`**: Replaced `(new Wallet)->newQuery()` with `Wallet::query()`.
- **`KycSubmission`, `User` models**: Added `declare(strict_types=1)` to match module convention.
- **`ProfileDashboard`**: Removed dead `resolveView()` pass-through method; calls `->layout()` directly on the view. Removed unused `Factory` / `View` imports.
- **`RegisterUserActionTest`**: Renamed to doc-specified method names. Fixed wallet balance assertion (`'0'` → `'0.00'`). Added `Event::assertDispatched(UserRegistered::class)`. Split wallet assertion into `test_wallet_is_created_after_registration`. Added `test_registration_fails_with_invalid_email` and `test_registration_fails_with_existing_username`.
- **`SubmitKycActionTest`**: Renamed to doc-specified method names. Fixed `'national_id'` → `'id_card'` (must match `ProfileDashboard` allowed values: `passport`, `id_card`, `drivers_license`). Added `Event::assertDispatched(UserKycSubmitted::class)` to the success test.
- **`01_identity_onboarding.md`**: Updated to reflect post-transaction event dispatch, explicit KYC document type list, corrected test assertion details, and added `UserKycSubmitted` no-listener gap note.

---

## ✅ Admin Operations Audit & Staff Activity Feature (v1.28)

Audited the full Admin & Operations flow against `documentation/05_admin_operations.md`. Fixed security gaps, resolved PHPStan errors, corrected a failing test, and delivered the Staff Activity Dashboard.

### Security Fixes
- **`WithdrawalAdmin::approve()` and `processPayout()`**: Added missing `$reviewer->can('approve', $withdrawal)` policy guard at the Livewire layer. Previously the `WithdrawalPolicy` four-eyes check was enforced inside the action but bypassed at the component level — inconsistent with the KYC pattern (`KycAdmin` correctly calls `$reviewer->can()` before delegating).
- **`ResolveDisputeAction`**: Changed signature from `int $resolvedByAdminUserId` to `User $actor`. Added `hasAnyRole(['ADMIN', 'SUPER_ADMIN'])` role guard. The action was the only one in the codebase without an authorization check; if called directly (outside `MatchAdmin`) there was no gate. Updated `MatchAdmin::resolveDispute()` to pass `Auth::user()` instead of `Auth::id()`.

### PHPStan Level 5 Fixes
- **`CmsAdmin`** (3 errors):
  - `published_at = now()` → `update(['published_at' => now()])` to avoid `Carbon` assigned to `string|null` property (the cast is on the model, not reflected in the bare property type).
  - `$translation?->name ?? ''` and `$translation?->content ?? ''` → explicit `@var ModelClass|null` PHPDoc + ternary, resolving `nullsafe.neverNull` from Larastan inferring `first()` as non-nullable on a typed `HasMany`.
- **`TournamentForm`** (2 errors):
  - `$tournament->rules` resolved to the `HasMany` relation collection (not the string column) due to PHPDoc `@property-read Collection|TournamentRule[] $rules`. Fixed by using `$tournament->getAttribute('rules')` to explicitly retrieve the raw column value.
  - `Game::first()?->id ?? 0` → `@var Game|null` PHPDoc + ternary, resolving `nullsafe.neverNull`.
- **`AdminPanelTest`** (1 error): Removed dead `$superAdmin` property that was written in `setUp()` but never read.

### Test Fix
- **`test_tournament_admin_can_create_tournament`**: Was calling `->test(TournamentAdmin::class)` and setting `$name`, which doesn't exist on `TournamentAdmin`. Tournament creation was extracted to `TournamentForm` in v1.4. Fixed to use `TournamentForm::class` and supplied all required fields (`platform_id`, `description`, `rules`, `frequency`, `team_size`, `waiting_result_time`).

### New Feature — Staff Activity Dashboard
- **`app/Livewire/Admin/StaffActivityDashboard.php`**: New Livewire component at `/admin/staff-activity`. Restricted to `ADMIN` and `SUPER_ADMIN` (enforced in `boot()`). Displays per-staff action counts with breakdown by event type, date-range and username filters (defaults to last 7 days), and a top-10 actions summary across all staff in the period.
- **`resources/views/livewire/admin/staff-activity-dashboard.blade.php`**: Dark-neon styled table matching admin panel design system. Shows staff member, role badge, total action count, inline action breakdown pills, and last-active timestamp.
- **Route**: Added `Route::get('/staff-activity', StaffActivityDashboard::class)->name('admin.staff-activity')` to the admin prefix group in `routes/web.php`.

### Documentation
- **`documentation/05_admin_operations.md`**: Updated to reflect all security fixes (policy guards, `ResolveDisputeAction` actor change), corrected `AssignRoleAction` note (SUPER_ADMIN only, not all admins), added Section 6 for Staff Activity Dashboard, updated test case list including new staff activity tests, and removed the `test_staff_redirect_from_player_dashboard` pending test (already covered by `test_admin_visiting_player_dashboard_redirects_to_admin_dashboard`).

### Tests
- **4 new tests** in `AdminPanelTest`: `test_player_cannot_access_staff_activity_dashboard`, `test_admin_can_access_staff_activity_dashboard`, `test_staff_activity_dashboard_shows_staff_members`, `test_staff_activity_dashboard_filters_by_date`, `test_staff_activity_dashboard_filters_by_staff_name`.
- **All 21 `AdminPanelTest` tests passing**. PHPStan Level 5: 0 errors across all modified files.

---

## 🔲 Pending / Not Yet Done

This section consolidates all known incomplete work across the project. For detailed task lists, see `PlayerSaloons_Execution_Checklist_v1.md`.

### Testing Debt
The following tests are identified but not yet written:

**Tournament & Admin UI**
- `test_admin_tournament_filter_persistence`
- `test_admin_frequency_tab_functionality` / `test_player_frequency_tab_functionality`
- `test_join_tournament_button_is_restricted_by_role`
- `test_tournament_listing_filters_by_status`
- `test_view_restricted_details_policy`
- `test_custom_pagination_rendering`
- `test_admin_navigation_flow` (wire:navigate SPA transitions)

**Livewire Component Tests**
- Elimination modal (4 cases: show/hide, go back, continue)
- My Tournaments stats banner calculation
- Elimination shifts tournament to history tab
- N+1 query prevention on `/my-tournaments`
- Player tournament list filtering
- H2H matchmaking simulation

### Feature Gaps (Not Implemented)
Items where schema or stub exists but logic is missing:

| Feature | Status | Notes |
|---|---|---|
| H2H Production Backend | ❌ Not started | UI is mock only. Schema, matchmaking engine, and escrow missing. |
| File Storage → R2/S3 | ⚠️ Deferred | Currently using local `public` disk. See deployment notes below. |
| External Payout Integration | ❌ Not started | `PROCESSED` state is manual. No PayPal/Stripe Connect. |
| Referral System Logic | ❌ Not started | Integer ref ID in DB, no reward logic. |
| 2FA | ❌ Not started | Schema has `two_factor_secret` but no UI/Action. |
| `last_login_at` | ❌ Not started | Column exists, not updated on login. |
| `UserKycSubmitted` listener | ❌ Not started | Event dispatched but no listener registered. |
| `deposits.fee_amount` | ❌ Not started | Field in DB and `$fillable`, but fee deduction not implemented. |
| Broadcast Messages UI | ❌ Not started | `broadcast_messages` table exists, no admin UI. |
| CMS Blog/News | ❌ Not started | — |
| Compliance/Blacklisting | ❌ Not started | — |
| Translation Management | ❌ Not started | — |
| Streaming Integration | ❌ Not started | `twitch_stream_url`/`youtube_stream_url` in schema, no live integration. |
| Team Tournaments | ❌ Not started | `tournament_registrations.team_id` placeholder unused. |
| Auto-Forfeit timeout config | ⚠️ Partial | `AutoForfeitJob` uses `waiting_result_time` but not exposed in `SystemSettings` UI. |

---

## 🚀 Deployment Notes

**First deployed**: 2026-06-18 via Docker Compose + Coolify on Linode (IP: 139.162.61.8)
**Domain**: app-testing.website (HTTPS — SSL active via Coolify/Let's Encrypt)

### Current Production Setup
- Docker Compose: `docker-compose.prod.yml`
- 5 containers: `app` (nginx + php-fpm), `reverb`, `worker` (Horizon), `scheduler`, `mysql`, `redis`
- `.env` loaded via Coolify environment variables UI

### Issues Encountered on First Deploy

**1. Login redirect loop (`/login?_token=...&identity=...&password=...`)**
- **Cause A**: No `trustProxies` configured — Laravel behind Coolify/Traefik didn't know it was receiving HTTPS-proxied requests, causing session/cookie inconsistency.
- **Fix A**: Added `$middleware->trustProxies(at: '*')` in `bootstrap/app.php`.
- **Cause B**: `SESSION_ENCRYPT=true` — encrypted sessions failed to decrypt on container restart/redeploy due to key loading inconsistency.
- **Fix B**: Set `SESSION_ENCRYPT=false`.
- **Cause C**: `SESSION_SECURE_COOKIE=true` but site was on HTTP — browser refused to send secure cookie over non-HTTPS.
- **Fix C**: Set `SESSION_SECURE_COOKIE=false` until SSL is configured.
- **Cause D**: Old session cookies in browser from local dev.
- **Fix D**: Clear browser cookies for the domain, or use incognito to test.

**2. `APP_URL=http://localhost`**
- Caused incorrect redirects and CSRF origin mismatches.
- Fix: Set `APP_URL=https://app-testing.website`.

### Pre-Deployment Checklist (For Future Deploys)

```
✅ bootstrap/app.php → trustProxies(at: '*') is present
✅ APP_URL = exact HTTPS domain
✅ APP_ENV = production
✅ APP_DEBUG = false
✅ SESSION_DRIVER = redis
✅ SESSION_DOMAIN = yourdomain.com
✅ SESSION_SECURE_COOKIE = true (only after SSL is active)
✅ SESSION_ENCRYPT = false
✅ REDIS_HOST = redis (container service name, not localhost)
✅ DB_HOST = mysql (container service name, not localhost)
✅ REVERB_HOST = domain name (not raw IP)
✅ REVERB_SCHEME = https, REVERB_PORT = 443 (after SSL)
✅ php artisan config:cache runs on deploy (handled in start.sh)
✅ php artisan migrate --force runs on deploy (handled in start.sh)
```

### SSL Setup (Coolify — Let's Encrypt)
1. In Coolify dashboard → your application → **Domains** section
2. Enter domain: `app-testing.website`
3. Enable HTTPS/SSL toggle (Coolify + Traefik handle Let's Encrypt automatically)
4. After SSL is active, update `.env`: `SESSION_SECURE_COOKIE=true`, `REVERB_SCHEME=https`, `REVERB_PORT=443`
5. Restart (not full redeploy needed for `.env`-only changes)

### Pending Production Work
- [x] Configure SSL via Coolify (Let's Encrypt) *(done v1.30)*
- [x] Update `SESSION_SECURE_COOKIE=true`, `REVERB_SCHEME=https`, `REVERB_PORT=443` after SSL *(done v1.30)*
- [x] Verify Horizon dashboard and queue workers are processing *(confirmed active v1.29)*
- [x] `php artisan storage:link` — handled in `start.sh` on deploy
- [ ] Migrate file storage to R2/S3 — deferred, see `execution_checklist.md` → File Storage Migration

---

## ✅ Match Confirmation Flow Alignment (v1.37)

- **`MatchStateMachine`**: Updated the canonical result flow to `IN_PROGRESS -> WAITING_FOR_CONFIRMATION -> COMPLETED/DISPUTED`. `RESULT_SUBMITTED` remains transition-compatible only for legacy rows.
- **`MatchAdmin` / `AdminDashboard`**: Aligned admin override and active-match counting with `WAITING_FOR_CONFIRMATION`.
- **Tests**: Updated match lifecycle tests to match auto-start behavior and the `ResolveDisputeAction` `User` actor contract. `MatchModuleTest`, `ConfirmResultFlowTest`, and `MatchStateMachineTest` pass: 24 tests, 75 assertions.
- **PHPStan**: Attempted at Level 5, but the runner exited with code 1 without diagnostics/output in this environment. PHP syntax checks passed for changed PHP files.

---

## ✅ Player Notification Bell (v1.36)

- **`NotificationBell`**: New Livewire component for the player dashboard topbar. Loads the latest 10 authenticated-user notifications, shows unread count/state, and supports single or bulk mark-as-read actions scoped through `auth()->user()->notifications()`.
- **Realtime frontend**: Added Laravel Echo + Pusher JS client wiring in `resources/js/app.js` for Laravel Reverb private user channels (`user.{uuid}`). Incoming `.notification.received` broadcasts dispatch a Livewire refresh event.
- **`dashboard.blade.php`**: Replaced static mock notification dropdown with the database-backed `<livewire:notification-bell />` component and exposes the authenticated user's UUID as a meta tag for Echo channel subscription.
- **Tests**: 6 tests in `NotificationBellTest`, all passing.
- **PHPStan**: 0 errors on changed PHP files at Level 5.

---

## ✅ Broadcast Notification Admin Panel (v1.35)

- **`BroadcastNotificationAdmin`**: New Livewire admin component at `/admin/notifications`. CRUD for `broadcast_messages` — create, edit, expire (all admins), delete permanently (SUPER_ADMIN only). Search by title/message, paginated list with status badges (Active/Scheduled/Expired).
- **Blade**: Modular partials — `_table.blade.php`, `_form-modal.blade.php`, `_confirm-modal.blade.php` — all reusable via `@include`.
- **`tests/TestCase.php`**: Added `withoutMiddleware(UpdateUserOnlineStatus::class)` globally to prevent Redis connection errors across all test HTTP requests.
- **`BroadcastMessage` model**: Added `@property` PHPDoc annotations + fixed `$fillable` to `list<string>`.
- **Tests**: 9 new tests in `BroadcastNotificationAdminTest` — access guards, create, edit, expire, delete (SUPER_ADMIN), search. All passing.
- **PHPStan**: 0 new errors at Level 5.

---

## ✅ Fix: missing `last_login_at` migration (v1.34)

- **`2026_06_19_112307_add_last_login_at_to_users_table.php`**: Added missing migration for `last_login_at` column on `users` table. Column was being updated in `Login.php` (since v1.29) but no migration existed — caused `SQLSTATE HY000: no such column` on local SQLite.
- **Tests**: No new tests — covered by existing login flow.
- **PHPStan**: N/A.

---

## ✅ Online presence tracking — Redis-based (v1.33)

- **`UpdateUserOnlineStatus`**: New middleware appended to `web` group. Sets `user_online:{id}` Redis key with 300s TTL on every authenticated request.
- **`User::isOnline()`**: New method — returns `true` if Redis key exists for the user.
- **`user-admin.blade.php`**: Added online dot indicator (emerald = online, slate = offline) next to username in the user list table.
- **Tests**: 4 new tests in `OnlinePresenceTest` — middleware sets key for auth user, skips guest, `isOnline()` true/false. All passing.
- **PHPStan**: 0 new errors at Level 5.

---

## ✅ Security tests — Tournament access controls (v1.32)

- **`TournamentSecurityTest`**: 3 new security-adjacent tests covering: Join button restricted to PLAYER role, player tournament listing filters out DRAFT/CANCELLED/COMPLETED statuses, and `viewRestrictedDetails` policy hides Matches/Players/Activity tabs from non-participants.
- **Tests**: 3 new tests, all passing.
- **PHPStan**: 0 errors at Level 5.

---

## ✅ NotifyAdminsOfKycSubmissionListener (v1.31)

- **`NotifyAdminsOfKycSubmissionListener`**: New queued listener on `notifications` queue. Handles `UserKycSubmitted` — sends an in-app notification to all users with `ADMIN` or `SUPER_ADMIN` role via `NotificationService`.
- **`EventServiceProvider`**: Registered `UserKycSubmitted → NotifyAdminsOfKycSubmissionListener`.
- **Tests**: 3 new tests in `NotifyAdminsOfKycSubmissionListenerTest` — admins notified, non-admins skipped, all admin roles covered. All passing.
- **PHPStan**: 0 errors on new files at Level 5.

---

## ✅ SSL env vars updated in Coolify (v1.30)

- **Deployment**: SSL now active on `app-testing.website`. Updated Coolify environment variables: `SESSION_SECURE_COOKIE=true`, `REVERB_SCHEME=https`, `REVERB_PORT=443`. Restarted via Coolify (no redeploy needed).
- **Tests**: No code changes — config-only update.
- **PHPStan**: N/A.

---

## ✅ Quick Fixes & Production Verification (v1.29)

### `last_login_at` — now updated on login
- **`app/Livewire/Auth/Login.php`**: Added `$user?->update(['last_login_at' => now()])` after successful `Auth::attempt()`.
- **`app/Modules/Identity/Models/User.php`**: Added `last_login_at` to `$fillable` and `casts` (`'datetime'`). Column already existed in the migration.
- **Usage**: Currently recorded but not yet displayed in UI. Planned use: Admin User panel (last seen), future online presence tracking.

### Horizon Production Verified
- `/horizon` accessible at production URL. Status: **Active**. Workers confirmed running via `worker` container.
- Zero metrics are expected until real user activity generates jobs.

### Documentation Reorganization
- Deleted redundant files: `PlayerSaloons_New_Admin_Features_Implementation_Plan_v1.md`, `PlayerSaloons_Baseline_Addendum_v1.md`, all `Zone.Identifier` artifacts.
- Moved all docs to `documentation/` folder (git mv — history preserved).
- Created `documentation/ONBOARDING.md` — single start-here file with setup, conventions, doc update format, data storage map, Definition of Done, and feature/bug tracking guide.
- Created `documentation/guides/r2-storage-migration.md` — step-by-step R2 migration guide (Obsidian-compatible).
- Updated `README.md` to point to ONBOARDING.md.
- Fixed `.gitignore` — removed incorrect ignores of `/documentation` folder and root doc files.
