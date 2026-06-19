# PlayerSaloons — Feature Map

**Last Updated**: 2026-06-19 (v1.32)

Quick-reference for developers. Maps every feature to its route, Livewire component, backend actions, and test coverage.

For architecture decisions and rationale, see `PlayerSaloons_Architecture_Baseline_v1.md`.
For step-by-step user flows and file-level details, see `/documentation/`.

---

## 🏗️ Core Architecture Principles
- **Modular Domain Design**: Business logic under `app/Modules/` by domain.
- **State Machine Driven**: All lifecycle transitions go through `AbstractStateMachine` classes.
- **Ledger-Based Finance**: Immutable `ledger_entries` are the source of truth for all balances.
- **Event-Driven**: Cross-module communication via thin domain events (no Eloquent models in events).
- **Security**: Granular RBAC via Spatie, UUIDs for external IDs, Four-Eyes checks for financial approvals.
- **Actions are self-authorizing**: Role/policy guards live inside Actions, not just at the component level.

---

## 🗺️ Route & Component Map

### Public Routes (unauthenticated)

| Route | Component | Description |
|---|---|---|
| `GET /` | `resources/views/welcome.blade.php` | Landing page |
| `GET /tournaments` | `app/Livewire/Tournament/PublicTournamentList.php` | Public tournament listing |
| `GET /login` | `app/Livewire/Auth/Login.php` | Login (guest only) |
| `GET /register` | `app/Livewire/Auth/Register.php` | Registration (guest only) |
| `GET /reset-password` | `app/Livewire/Auth/PasswordReset.php` | Password reset (guest only) |

### Player Routes (auth required)

| Route | Component | Description |
|---|---|---|
| `GET /dashboard` | `app/Livewire/Dashboard/PlayerDashboard.php` | Cockpit overview: balance, recent matches, upcoming tournaments |
| `GET /my-tournaments` | `app/Livewire/Tournament/MyTournamentsList.php` | Player's active + history tournaments with stats banner |
| `GET /tournaments/browse` | `app/Livewire/Tournament/PlayerTournamentList.php` | Browse & filter all active tournaments |
| `GET /tournaments/{uuid}/view` | `app/Livewire/Tournament/TournamentDetail.php` | Tournament detail, registration, check-in, bracket, matches |
| `GET /matches/{uuid}` | `app/Livewire/Match/MatchDetail.php` | Match lobby: result submission, evidence, dispute |
| `GET /head-to-head` | `app/Livewire/Match/HeadToHeadList.php` | H2H duel UI (mock/prototype) |
| `GET /leaderboards` | `app/Livewire/Match/LeaderboardList.php` | Leaderboard (stub) |
| `GET /streams` | `app/Livewire/Stream/StreamList.php` | Streams (stub) |
| `GET /chat` | `app/Livewire/Community/GlobalChat.php` | Global chat (mock) |
| `GET /wallet` | `app/Livewire/Wallet/WalletDashboard.php` | Wallet balance + transaction history |
| `GET /profile` | `app/Livewire/Profile/ProfileDashboard.php` | Profile settings, KYC upload, notification prefs |
| `GET /teams` | `app/Livewire/Team/TeamDashboard.php` | Team management: create, invite, roster, captaincy |
| `GET /verify-email` | `app/Livewire/Auth/EmailVerification.php` | Email verification notice |
| `POST /logout` | inline route closure | Invalidates session and redirects to `/` |

### Admin Routes (auth + ADMIN/SUPER_ADMIN role)

| Route | Component | Description |
|---|---|---|
| `GET /admin` | `app/Livewire/Admin/AdminDashboard.php` | Stats grid + recent KYC/withdrawal activity |
| `GET /admin/profile` | `app/Livewire/Admin/AdminProfile.php` | Staff profile |
| `GET /admin/tournaments` | `app/Livewire/Admin/TournamentAdmin.php` | Tournament list + lifecycle state transitions |
| `GET /admin/tournaments/create` | `app/Livewire/Admin/TournamentForm.php` | 4-step creation wizard |
| `GET /admin/tournaments/{id}/edit` | `app/Livewire/Admin/TournamentForm.php` | Edit tournament (limited edit for published) |
| `GET /admin/matches` | `app/Livewire/Admin/MatchAdmin.php` | Match list + result override + dispute resolution |
| `GET /admin/kyc` | `app/Livewire/Admin/KycAdmin.php` | KYC queue: approve/reject submissions |
| `GET /admin/withdrawals` | `app/Livewire/Admin/WithdrawalAdmin.php` | Withdrawal queue: review/approve/reject/process |
| `GET /admin/users` | `app/Livewire/Admin/UserAdmin.php` | User list: suspend, roles, wallet view |
| `GET /admin/audit-logs` | `app/Livewire/Admin/AuditLogAdmin.php` | Spatie activity log viewer with filters |
| `GET /admin/cms` | `app/Livewire/Admin/CmsAdmin.php` | Games, Platforms, CMS Pages management |
| `GET /admin/staff-activity` | `app/Livewire/Admin/StaffActivityDashboard.php` | Per-staff action breakdown (ADMIN/SUPER_ADMIN) |

### REST API Routes (`/api/v1`, Sanctum auth)

| Endpoint | Controller | Description |
|---|---|---|
| `GET /api/v1/tournaments` | `TournamentApiController` | Paginated list with filters |
| `GET /api/v1/tournaments/{uuid}` | `TournamentApiController` | Tournament detail |
| `POST /api/v1/tournaments/{uuid}/register` | `TournamentApiController` | Register for tournament |
| `POST /api/v1/tournaments/{uuid}/checkin` | `TournamentApiController` | Check in to tournament |
| `GET /api/v1/matches/{uuid}` | `MatchApiController` | Match detail |
| `POST /api/v1/matches/{uuid}/result` | `MatchApiController` | Submit match result |
| `POST /api/v1/matches/{uuid}/dispute` | `MatchApiController` | Open dispute |
| `GET /api/v1/wallet` | `WalletApiController` | Wallet balance |
| `GET /api/v1/wallet/ledger` | `WalletApiController` | Transaction ledger (paginated) |
| `POST /api/v1/wallet/withdraw` | `WalletApiController` | Request withdrawal (KYC required) |
| `GET /api/v1/profile` | `ProfileApiController` | View profile |
| `PUT /api/v1/profile` | `ProfileApiController` | Update profile |
| `POST /api/v1/teams` | `TeamApiController` | Create team |
| `GET /api/v1/teams/{uuid}` | `TeamApiController` | Team detail |
| `POST /api/v1/teams/{uuid}/invite` | `TeamApiController` | Invite player |
| `GET /api/v1/notifications` | `NotificationApiController` | Notification list (paginated) |
| `POST /api/v1/notifications/{id}/read` | `NotificationApiController` | Mark as read |

---

## 📦 Feature → Backend Mapping

### Identity & Onboarding
| Feature | Action/Service | Event | Listener |
|---|---|---|---|
| Register | `RegisterUserAction` | `UserRegistered` | `CreateWalletListener` |
| KYC Submit | `SubmitKycAction` | `UserKycSubmitted` | `NotifyAdminsOfKycSubmissionListener` |
| KYC Approve | `ApproveKycAction` | `UserKycApproved` | — |
| KYC Reject | `RejectKycAction` | `UserKycRejected` | — |
| Suspend User | `SuspendUserAction` | `UserSuspended` | — |
| Update Profile | `UpdateProfileAction` | — | — |
| Upload Avatar | `UploadAvatarAction` | — | — |

### Tournament Lifecycle
| Feature | Action/Service | Event | Listener/Job |
|---|---|---|---|
| Register for tournament | `RegisterForTournamentAction` | `TournamentSeatReserved` | `TournamentNotificationListener` |
| Check-in | `CheckinParticipantAction` | `PlayerCheckedIn` | — |
| Close registration | `CloseRegistrationAction` | `TournamentRegistrationClosed` | — |
| Generate bracket | `BracketGenerationService` | `TournamentBracketGenerated` | — |
| Start tournament | `StartTournamentAction` | `TournamentStarted` | `AutoStartMatchesListener`, `BroadcastTournamentLifecycleListener` |
| Auto-cancel | — | — | `AutoCancelTournamentJob` |
| Complete tournament | — | `TournamentCompleted` | `AwardPrizesListener` |
| Cancel + refund | `CancelTournamentAction` + `ProcessRefundAction` | `TournamentCancelled` | `IssueRefundsListener` |

### Match Execution
| Feature | Action/Service | Event | Listener/Job |
|---|---|---|---|
| Submit result | `SubmitMatchResultAction` | `MatchResultSubmitted` | `NotifyParticipantsListener` |
| Confirm result | `ConfirmMatchResultAction` | `MatchCompleted` | `AdvanceWinnerListener`, `BroadcastBracketUpdateListener` |
| Auto-forfeit | `ForfeitMatchAction` | `MatchForfeited` | — |
| Open dispute | `OpenDisputeAction` + `SubmitEvidenceAction` | `MatchDisputed` | — |
| Resolve dispute | `ResolveDisputeAction` | `MatchCompleted` | `AdvanceWinnerListener` |
| Auto-start | — | — | `AutoStartMatchesListener` (on `TournamentStarted` + `MatchCompleted`) |
| Auto-forfeit timeout | — | — | `AutoForfeitJob` (scheduler, every minute) |

### Wallet & Finance
| Feature | Action/Service | Event | Listener |
|---|---|---|---|
| Deposit | `ProcessDepositAction` | `WalletCredited` | `SendDepositNotificationListener` |
| Request withdrawal | `RequestWithdrawalAction` | `WithdrawalRequested` | — |
| Approve withdrawal | `ApproveWithdrawalAction` | `WithdrawalApproved` | `CreateLedgerEntryListener` (debit) |
| Process withdrawal | `ProcessWithdrawalAction` | — | — |
| Prize award | `AwardPrizesListener` | `PrizeAwarded` | `TournamentNotificationListener` |
| Entry fee | `WalletService::debit()` (inside RegisterForTournamentAction) | `EntryFeeCollected` | — |

### Team Management
| Feature | Action/Service | Event | Job |
|---|---|---|---|
| Create team | `CreateTeamAction` | `TeamCreated` | — |
| Invite member | `InviteToTeamAction` | `TeamMemberInvited` | `ExpireTeamInvitationsJob` |
| Accept invite | `AcceptTeamInvitationAction` | `TeamMemberJoined` | — |
| Decline invite | `DeclineTeamInvitationAction` | — | — |
| Revoke invite | `RevokeTeamInvitationAction` | — | — |
| Remove member | `RemoveTeamMemberAction` | `TeamMemberRemoved` | — |
| Transfer captain | `TransferTeamCaptainAction` | `TeamCaptainChanged` | — |
| Disband team | `DisbandTeamAction` | `TeamDeleted` | — |

---

## 🧪 Test Coverage Map

### Feature Tests (`tests/Feature/`)
| Test File | What It Covers |
|---|---|
| `Identity/RegisterUserActionTest.php` | Registration success, wallet creation, event dispatch, validation failures |
| `Identity/SubmitKycActionTest.php` | KYC submission, resubmission from rejected, event dispatch |
| `Identity/NotifyAdminsOfKycSubmissionListenerTest.php` | Admin notification on KYC submit, non-admins not notified, all admin roles notified |
| `Authorization/PolicyTest.php` | All 8 policies across Tournament, Match, Wallet, Withdrawal, KYC, Team, User, Dispute |
| `Api/ApiEndpointsTest.php` | 401/403 gates, pagination, status filters, referral URL format |
| `Community/NotificationServiceTest.php` | Preference-aware delivery (in-app, realtime, email) |
| `Admin/AdminPanelTest.php` | Admin access guards, KYC approve/reject, match override, tournament create (TournamentForm), staff activity |
| `Wallet/WalletFeatureTest.php` | Deposit idempotency, withdrawal lifecycle, ledger sum = cached balance, listener idempotency |
| `Tournament/TournamentModuleTest.php` | Registration, check-in, bracket generation, cancellation, refunds, prize distribution |
| `Tournament/TournamentSecurityTest.php` | Join button role restriction, listing status filter, viewRestrictedDetails policy |
| `Match/MatchModuleTest.php` | Result submission, confirmation flow, dispute, forfeit, bracket advancement |
| `Match/ConfirmResultFlowTest.php` | Full flow: confirmResult → MatchCompleted → AdvanceWinnerListener + AutoForfeitJob timeout |
| `Team/TeamModuleTest.php` | All 11 team actions: create, invite, accept, decline, revoke, remove, transfer, disband |

### Unit Tests (`tests/Unit/`)
| Test File | What It Covers |
|---|---|
| `StateMachines/TournamentStateMachineTest.php` | All transitions including rollbacks, guards |
| `StateMachines/MatchStateMachineTest.php` | All match state paths including dispute/forfeit |
| `StateMachines/WalletStateMachineTest.php` | ACTIVE/SUSPENDED/FROZEN transitions + SUPER_ADMIN guard |
| `StateMachines/WithdrawalStateMachineTest.php` | Full approval pipeline, four-eyes guard, null-safety |
| `StateMachines/KycStateMachineTest.php` | NOT_SUBMITTED → SUBMITTED → APPROVED/REJECTED |
| `StateMachines/InvitationStateMachineTest.php` | PENDING → ACCEPTED/DECLINED/EXPIRED/REVOKED |
| `StateMachines/SeatReservationStateMachineTest.php` | RESERVED → CONFIRMED/EXPIRED/CANCELLED |
| `Tournament/BracketGenerationServiceTest.php` | 2, 5, 6, 8-player bracket sizes with bye math |

### Pending Tests (Not Yet Written)
See `PlayerSaloons_Execution_Checklist_v1.md` → Testing Debt section for the full list.

---

## 🛠️ Technical Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11+ |
| Frontend | Livewire 3 + Alpine.js |
| Realtime | Laravel Reverb (WebSockets) |
| CSS | Tailwind CSS v4 |
| Icons | Lucide Icons |
| Auth | Laravel session auth + Sanctum (API) |
| RBAC | Spatie Laravel Permission |
| Audit Logging | Spatie Laravel Activity Log |
| Queue/Jobs | Laravel Horizon (Redis) |
| Database | MySQL 8 (production) / SQLite (local dev) |
| Cache/Session | Redis |
| File Storage | Local `public` disk (dev/staging) → R2/S3 (production, pending) |
| Testing | PHPUnit (Feature + Unit) |
| Static Analysis | PHPStan Level 5–8 (Larastan) |
| Deployment | Docker Compose + Coolify (Linode) |
