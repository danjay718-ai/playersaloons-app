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
  - `CreditWalletAction`, `DebitWalletAction`: High-level entry points for balance adjustment.
  - `CollectEntryFeeAction`: Reserves entry fee from player wallet; atomically moves to platform escrow.
  - `AwardPrizeAction`: Distributes prize from platform escrow to winner wallet with ledger audit.
  - `RequestWithdrawalAction`: Validates balance and KYC before queueing withdrawal.
  - `ProcessDepositAction`: Integrates with external payment provider webhooks (Stripe/PayPal mock).
- **Invariants**:
  - Wallet balance can NEVER go negative.
  - Ledger entries are the **single source of truth** (balance is cached but derived from ledger).
- **Tests**: 100% pass on all financial pipelines.

---

## ✅ Phase 16 — Admin Panel (Livewire)

Full-featured internal operations dashboard for staff (ADMIN / SUPER_ADMIN roles). Built on Livewire 3 with a dedicated admin layout at `resources/views/components/layouts/admin.blade.php`.

- **Base Class (`app/Livewire/Admin/AdminComponent.php`)**: Staff-only access enforcement.
- **Admin Layout**: Responsive professional dark theme with Flash notifications.
- **Tournament Admin (`/admin/tournaments`)**: Search, state-transitions, cancel logic.
- **Match Admin (`/admin/matches`)**: Dispute resolution and result overrides.
- **KYC & Withdrawal Admins**: Operational queues with review workflows.
- **Audit Log Admin**: Full system visibility and filtering.

- **Advanced Tournament Features & Performance (v1.5)**:
  - **Modal Optimization**: Integrated Alpine.js for instant modal visibility and backdrop control. Optimized server-side `render()` logic to prevent unnecessary relationship loading when modals are closed. Added `wire:loading` states and skeletons.
  - **Reusable Action Dropdown**: Created a reusable `x-admin.action-dropdown` Blade component for consistent 'kebab' action menus across all admin tables.
  - **Limited Edit Mode**: Implemented "Limited Edit" (Option A) for tournaments, allowing admins to update rules, descriptions, and schedules for published tournaments while locking critical financial/structural fields.
  - **Multi-Step Tournament Wizard**: Refactored the tournament creation/edit form into a 4-step interactive wizard (Identity, Settings, Schedule, Prizes) with strict per-step validation and real-time button disabling.
  - **Rich Text Integration**: Integrated **Quill Rich Text Editor** for tournament descriptions and rules with optimized deferred synchronization to eliminate typing lag.
  - **Expanded Filters & Schedules**: Added "One-time / Single Event" frequency option. Made Platform selection mandatory and implemented Platform/Frequency filters on both Admin and Player tournament lists.
  - **Draft Persistence**: Implemented local persistence using `localStorage` to automatically save tournament drafts, preventing data loss during creation.
  - **Scheduling Guidance**: Added instructional helper notes to Step 3 of the wizard to guide admins on chronological date requirements (Registration < Check-in < Start).

- **Tests**:
  - `tests/Feature/Admin/AdminPanelTest.php`: 12 feature tests covering CRUD & lifecycle.
  - `tests/Feature/Auth/LoginRedirectTest.php`: 7 tests for role-based routing.
  - **All 154 tests pass at 100%.**
