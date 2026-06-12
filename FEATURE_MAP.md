# PlayerSaloons — Feature Map & Technical Documentation

This document provides a comprehensive mapping of features, implementation phases, and their associated files, as defined by the **Architecture Baseline v1** and documented in **Project Progress**.

---

## 🏗️ Core Architecture Principles
- **Modular Domain Design**: Organized by business capability under `app/Modules/`.
- **State Machine Driven**: All lifecycle transitions are governed by explicit State Machine classes.
- **Ledger-Based Finance**: Immutable `ledger_entries` are the source of truth for all wallet balances.
- **Event-Driven**: Cross-module communication occurs via thin domain events.
- **Security First**: Granular RBAC, UUIDs for external exposure, and Four-Eyes checks for financial approvals.

---

## 📅 Implementation Phases

### Phase 1: Migrations & Seeders
Defines the foundational database schema and initial system state across all 8 domains.
- **Files**:
    - `database/migrations/*.php`: 45 files defining tables for Identity, Wallet, Tournament, Match, Team, etc.
    - `database/seeders/*.php`: `RolesAndPermissionsSeeder`, `GamesTableSeeder`, `SystemSettingsSeeder`.
- **Documentation**: Establishes relational integrity, unique constraints, and initial RBAC configuration.

### Phase 2: Eloquent Models
Implements the domain layer with strict typing and integrity hooks.
- **Files**:
    - `app/Modules/*/Models/*.php`: 32 models (e.g., `User`, `Tournament`, `Wallet`, `GameMatch`).
- **Documentation**: Includes **Immutable Models** (e.g., `LedgerEntry`, `Refund`) that prevent updates/deletions via Eloquent `booted()` hooks to ensure audit trail integrity.

### Phase 3: Laravel Enums
Standardizes statuses and types across the application.
- **Files**:
    - `app/Shared/Enums/*.php`: 14 backed enums (e.g., `TournamentStatus`, `MatchStatus`, `WalletStatus`).
- **Documentation**: Used in models, state machines, and API resources to ensure consistent type safety.

### Phase 4: State Machines
The core engine for workflow-driven entities.
- **Files**:
    - `app/Modules/*/StateMachines/*.php`: `TournamentStateMachine`, `MatchStateMachine`, `WalletStateMachine`, `WithdrawalStateMachine`, `KycStateMachine`, `InvitationStateMachine`.
- **Documentation**: Enforces valid lifecycle transitions; throws `LogicException` on illegal moves.

### Phase 5: Domain Events
Thin, immutable events for decoupling modules.
- **Files**:
    - `app/Modules/*/Events/*.php`: 36 events (e.g., `UserRegistered`, `TournamentStarted`, `PrizeAwarded`).
    - `app/Shared/Events/DomainEvent.php`: Base class providing `occurredAt` and `Dispatchable` traits.
- **Documentation**: Events carry identifiers only (no full models) to minimize payload size and context coupling.

### Phase 6: Identity Module
Handles user lifecycle, profiles, and regulatory compliance.
- **Files**:
    - `app/Modules/Identity/Actions/`: `RegisterUserAction`, `SubmitKycAction`, `ReviewKycAction`.
    - `app/Modules/Identity/Models/User.php`: Custom user model with identity traits.
- **Documentation**: Atomically manages user/profile creation and multi-step KYC document workflows.

### Phase 7: Wallet Service
A high-integrity ledger-based financial system.
- **Files**:
    - `app/Modules/Wallet/Services/WalletService.php`: Central logic for credits, debits, and balance sync.
    - `app/Modules/Wallet/Actions/`: `RequestWithdrawalAction`, `ProcessDepositAction`.
- **Documentation**: Maintains `cached_balance` on `wallets` table while summing `ledger_entries` for absolute truth.

### Phase 8: Tournament Module
Manages the competitive lifecycle and bracket orchestration.
- **Files**:
    - `app/Modules/Tournament/Actions/`: `CreateTournamentAction`, `OpenRegistrationAction`, `CloseCheckinAction`.
    - `app/Modules/Tournament/Services/BracketGenerationService.php`: Single-elimination algorithm with "bye" support.
- **Documentation**: Handles prize pool calculations, seat reservations, and automated refunds upon cancellation.

### Phase 9: Match Module
Executes match-level logic, results, and disputes.
- **Files**:
    - `app/Modules/Match/Actions/`: `SubmitMatchResultAction`, `OpenDisputeAction`, `ResolveDisputeAction`.
    - `app/Modules/Match/Listeners/AdvanceWinnerListener.php`: Automates bracket progression.
- **Documentation**: Manages match execution states and provides evidence-based dispute resolution workflows.

### Phase 10: Team Module
Provides social and competitive grouping features.
- **Files**:
    - `app/Modules/Team/Actions/`: `CreateTeamAction`, `InviteToTeamAction`, `AcceptTeamInvitationAction`.
    - `app/Modules/Team/Jobs/ExpireTeamInvitationsJob.php`: Cleans up stale invites.
- **Documentation**: Manages roster controls, captaincy transfers, and invitation lifecycles.

### Phase 11: Scheduler Automation
Background automation for platform-wide time-sensitive tasks.
- **Files**:
    - `routes/console.php`: Scheduler definitions.
    - `app/Modules/Tournament/Jobs/`: `OpenCheckinJob`, `StartTournamentJob`, `AutoCancelTournamentJob`.
- **Documentation**: Runs every minute to sweep the system for tournaments needing state updates or auto-cancellation.

### Phase 12: Notifications & Realtime
Real-time user engagement and platform updates.
- **Files**:
    - `app/Modules/Community/Services/NotificationService.php`: Preference-aware delivery engine.
    - `app/Modules/Community/Broadcasting/`: `BroadcastTournamentStarted`, `BroadcastBracketUpdate`.
- **Documentation**: Integrates with **Laravel Reverb** for WebSocket-based UI updates and respects user notification toggles.

### Phase 13: Authorization (RBAC)
Granular, policy-based access control.
- **Files**:
    - `app/Modules/*/Policies/`: `TournamentPolicy`, `WithdrawalPolicy`, `MatchPolicy`, etc.
    - `app/Providers/AppServiceProvider.php`: Manual policy registration mappings.
- **Documentation**: Implements **Four-Eyes principle** for withdrawals (reviewer cannot be the requester) and restricts match actions to involved participants.

### Phase 14: API Layer
Public and authenticated RESTful interface.
- **Files**:
    - `app/Http/Controllers/Api/V1/`: `TournamentApiController`, `MatchApiController`, `WalletApiController`.
    - `app/Http/Resources/`: JSON serialization layers (UUID exposure, hiding internal IDs).
- **Documentation**: Exposes `/api/v1` endpoints with Sanctum middleware and semantic error handling for state transitions.

### Phase 15: Livewire UI (Frontend & Dashboard)
Modern, high-fidelity user interface.
- **Files**:
    - `app/Livewire/Dashboard/PlayerDashboard.php`: Central player hub.
    - `app/Livewire/Profile/ProfileDashboard.php`: Settings and KYC upload UI.
    - `resources/views/welcome.blade.php`: "Play. Win. Cash." landing page.
- **Documentation**: Uses **Tailwind CSS v4** and **Lucide Icons** with a dark-neon aesthetic ("Orbitron" for headers, "Inter" for body).

### Phase 16: Admin Panel (Livewire)
Operational command center for staff.
- **Files**:
    - `app/Livewire/Admin/AdminDashboard.php`: Platform-wide stats overview.
    - `app/Livewire/Admin/TournamentAdmin.php`: Manual lifecycle control for staff.
    - `app/Livewire/Admin/KycAdmin.php`: Document review and approval workflow.
- **Documentation**: Enforces staff-only access via `AdminComponent` base class; provides overrides for matches and dispute resolution.

---

## 🛠️ Technical Stack Summary
| Layer | Technology |
|---|---|
| Framework | Laravel 11+ |
| Realtime | Laravel Reverb |
| Frontend | Livewire 3 + AlpineJS |
| CSS | Tailwind CSS v4 |
| Accounting | Double-entry / Ledger-based |
| Database | MySQL 8 (Storage) + Redis (Cache/Queue) |
| Testing | PHPUnit (Feature & Unit) |

---

## 🕹️ User Flows & File Mappings (Debugging Guide)

Use this section to identify which files to review or debug based on the specific user journey.

### 1. Player Onboarding & Identity
| Step | Action / UI | Key Files to Review / Debug |
|---|---|---|
| **Registration** | `Register.php` (LW) | `RegisterUserAction`, `UserRegistered` (Event), `User` (Model), `AssignRoleAction` |
| **Email Verification** | `EmailVerification.php` (LW) | `User` (Model), `DomainEvent` |
| **KYC Submission** | `ProfileDashboard.php` (LW) | `SubmitKycAction`, `KycSubmission` (Model), `KycStateMachine` |
| **Profile Updates** | `ProfileDashboard.php` (LW) | `UpdateProfileAction`, `UploadAvatarAction`, `UserProfile` (Model) |

### 2. Tournament Lifecycle (Player Perspective)
| Step | Action / UI | Key Files to Review / Debug |
|---|---|---|
| **Discovery** | `TournamentList.php` (LW) | `Tournament` (Model), `TournamentResource` (API) |
| **Registration** | `TournamentDetail.php` (LW) | `RegisterForTournamentAction`, `TournamentRegistration` (Model), `WalletService` (Fee collection) |
| **Check-in** | `PlayerDashboard.php` (LW) | `CheckinParticipantAction`, `TournamentCheckin` (Model), `CheckinStatus` (Enum) |
| **Match Play** | `MatchDetail.php` (LW) | `GameMatch` (Model), `MatchStateMachine`, `StartMatchAction` |
| **Submit Result** | `MatchDetail.php` (LW) | `SubmitMatchResultAction`, `MatchResultSubmission` (Model), `AdvanceWinnerListener` |
| **Open Dispute** | `MatchDetail.php` (LW) | `OpenDisputeAction`, `MatchDispute` (Model), `SubmitEvidenceAction` |

### 3. Financial Operations
| Step | Action / UI | Key Files to Review / Debug |
|---|---|---|
| **Deposit** | `ProcessDepositAction` | `Deposit` (Model), `WalletService`, `LedgerEntry` (Model), `WalletCredited` (Event) |
| **Withdrawal Request** | `WalletApiController` | `RequestWithdrawalAction`, `Withdrawal` (Model), `WithdrawalStateMachine`, `WalletPolicy` |
| **Balance View** | `dashboard.blade.php` | `Wallet` (Model), `cached_balance` sync logic in `WalletService` |

### 4. Team Management
| Step | Action / UI | Key Files to Review / Debug |
|---|---|---|
| **Create Team** | `TeamDashboard.php` (LW) | `CreateTeamAction`, `Team` (Model), `TeamMember` (Model) |
| **Invite Member** | `TeamDashboard.php` (LW) | `InviteToTeamAction`, `TeamInvitation` (Model), `InvitationStateMachine` |
| **Accept Invite** | `TeamDashboard.php` (LW) | `AcceptTeamInvitationAction`, `TeamMemberJoined` (Event) |

### 5. Admin & Operations (Staff Perspective)
| Flow | Admin Component | Key Files to Review / Debug |
|---|---|---|
| **KYC Review** | `KycAdmin.php` (LW) | `ApproveKycAction`, `RejectKycAction`, `KycStateMachine`, `KycPolicy` |
| **Withdrawal Approval** | `WithdrawalAdmin.php` (LW) | `ApproveWithdrawalAction`, `ProcessWithdrawalAction`, `WithdrawalStateMachine`, `WithdrawalPolicy` |
| **Tournament Control** | `TournamentAdmin.php` (LW) | `CreateTournamentAction`, `CancelTournamentAction`, `TournamentStateMachine`, `TournamentPolicy` |
| **Match Management** | `MatchAdmin.php` (LW) | `ResolveDisputeAction`, `ForfeitMatchAction`, `MatchStateMachine`, `MatchPolicy` |
| **User Moderation** | `UserAdmin.php` (LW) | `SuspendUserAction`, `UnsuspendUserAction`, `AssignRoleAction`, `UserPolicy` |
| **Audit Oversight** | `AuditLogAdmin.php` (LW) | `AuditLog` (Model), `AuditLogCreated` (Event) |

---

## 🔍 Debugging Documentation

### 🛡️ State Machine Failures
- **Symptom**: "Invalid state transition" error or action not firing.
- **Check**: Review the `transition()` method in the relevant `app/Modules/*/StateMachines/*.php` file. Ensure the current status allows the target transition.
- **Tip**: Check `LogicException` in `storage/logs/laravel.log` to see which guard failed.

### 💰 Wallet & Ledger Inconsistencies
- **Symptom**: User balance looks wrong or transaction failed.
- **Check**: Compare `wallets.cached_balance` against `SELECT SUM(amount) FROM ledger_entries WHERE wallet_id = ?`.
- **Integrity**: Financial logic is in `WalletService.php`. Every credit/debit **must** have a corresponding `LedgerEntry`.

### 🔏 Authorization (RBAC) Issues
- **Symptom**: 403 Forbidden even with the correct role.
- **Check**: Review `app/Modules/*/Policies/*.php`. Verify that the policy is registered in `AppServiceProvider.php` under `Gate::policy()`.
- **Staff Access**: Ensure the user has the `ADMIN` or `SUPER_ADMIN` role via the `user_roles` pivot table.

### ⚡ Livewire & UI Issues
- **Symptom**: UI not updating or "Component not found".
- **Check**: Ensure the component is in `app/Livewire` and the view is in `resources/views/livewire`.
- **Reverb**: For real-time updates (brackets/notifications), check if `php artisan reverb:start` is running and the `Broadcast` event is dispatched.

### ⚙️ Automation & Jobs
- **Symptom**: Tournaments not starting or check-ins not closing.
- **Check**: `php artisan queue:listen`. Review `app/Modules/*/Jobs/*.php`.
- **Logs**: Check the `job_execution_logs` table in the database for failure payloads and error messages.

