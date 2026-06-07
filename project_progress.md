# PlayerSaloons — MVP Progress

**Last Updated**: 2026-06-07 | **Branch**: `main`

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

**7 state machines** under `app/Modules/*/StateMachines/`, extending [`AbstractStateMachine`](file:///home/danjay/Projects/playersaloons-app/app/Shared/StateMachines/AbstractStateMachine.php):

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
