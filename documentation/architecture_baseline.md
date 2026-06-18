# PlayerSaloons — Architecture Baseline

**Last Updated**: 2026-06-18 (v1.28) | **Original Baseline**: 2026-06-14

## 🏗️ Architectural Overview

PlayerSaloons is a modular monolithic Laravel application structured by domain-driven design principles.

### Core Modules (`app/Modules/`)
- **CMS**: Content, Games, Platforms, Pages.
- **Identity**: Users, Profiles, KYC, RBAC (via Spatie).
- **Tournament**: Templates, Management, Registration, Check-in, Brackets, Matches.
- **Match**: Match execution, Disputes, Evidence.
- **Wallet**: Ledgers, Balances, Transactions, Withdrawals, Prizes.
- **Team**: Team management, Invitations.
- **Community**: Notifications, Chat, Streams.

### Infrastructure & Patterns
- **State Machines**: All core entities (`Tournament`, `Match`, `Wallet`, `Withdrawal`, `Kyc`, `Invitation`, `SeatReservation`) utilize domain-specific state machine classes (`AbstractStateMachine`) to enforce strict lifecycle transitions.
- **Actions/Services**: Business logic is encapsulated in `Actions` and `Services` within each module. Controllers/Livewire components strictly delegate to these.
- **Domain Events**: Immutable events used for cross-module communication to reduce tight coupling.
- **Livewire 3**: Frontend-heavy features use Livewire 3 components for reactive UI.
- **Layouts**: Configurable layout injection via `layout` prop or component methods, ensuring theme consistency (Admin vs Player) while sharing high-fidelity UI components.

---

## 🚀 Architectural & Lifecycle Updates (v1.14)

### 1. UI/UX Refactoring & Modularization
- **Modularization**: Monolithic `PlayerDashboard` broken down into dedicated Livewire components/pages (`/my-tournaments`, `/tournaments/browse`, `/head-to-head`, `/leaderboards`, `/streams`, `/chat`).
- **Dashboard Redesign**: Simplified dashboard into a lightweight "Cockpit" widget overview.
- **Theme Consistency**: High-fidelity dark-neon dashboard theme applied consistently across both Player and Admin contexts using configurable layouts.
- **UI Components**: Extracted common patterns (e.g., Delete Modal, Shared UI Partial) into reusable Blade components/partials.

### 2. Tournament Lifecycle & Logic
- **State Management**: Implemented valid state transition rollbacks in `TournamentStateMachine`.
- **Validation**: Strict guards (`guardCanCloseCheckin`) enforced on state transitions.
- **Routing**: Explicit separation of Public vs Player-side tournament routes using context-specific components.

---

## 📐 Post-v1.14 Architectural Changes

Changes here represent deviations or additions to the original baseline design. Each entry explains what changed, why it was changed, and which part of the baseline it relates to.

---

### [v1.15–v1.16] Match Confirmation & Auto-Forfeit

**Baseline reference**: "State Machines govern match lifecycle" — originally assumed manual admin intervention for stale matches.

**What changed**:
- `MatchStateMachine` gained a `WAITING_FOR_CONFIRMATION` state between `RESULT_SUBMITTED` and `COMPLETED`.
- `ConfirmMatchResultAction` — opponent must explicitly confirm before a match completes.
- `AutoForfeitJob` — scheduled job auto-resolves matches stuck in `WAITING_FOR_CONFIRMATION` past `tournament.waiting_result_time`.
- `AutoStartMatchesListener` — READY matches auto-transition to IN_PROGRESS when a tournament starts or a player advances, removing the need for a manual `StartMatchAction`.

**Why**: Prevents unilateral result manipulation; adds integrity to the match resolution flow. The baseline assumed simpler result submission without a two-party confirmation gate.

---

### [v1.16] Scheduler as Infrastructure

**Baseline reference**: Scheduler listed as Phase 11 automation — originally scoped to tournament lifecycle jobs only.

**What changed**:
- `AutoForfeitJob` added to the scheduler (runs every minute).
- `ExpireTeamInvitationsJob` added to the scheduler.
- Production deployment requires both a cron running `php artisan schedule:run` AND a persistent queue worker. In Docker this is handled by dedicated `scheduler` and `worker` containers (see `docker-compose.prod.yml`).

**Why**: As more jobs were added, the scheduler became a first-class infrastructure concern, not just an automation feature.

---

### [v1.4] Tournament Creation Extracted to Dedicated Page

**Baseline reference**: Admin Panel — tournament CRUD was a modal inside `TournamentAdmin`.

**What changed**:
- Tournament creation/editing extracted from a modal to a dedicated `TournamentForm` Livewire component at `/admin/tournaments/create` and `/admin/tournaments/{id}/edit`.
- Uses `wire:navigate` for SPA-like transitions.
- Multi-step wizard (4 steps: Identity, Settings, Schedule, Prizes).

**Why**: Modal UX was insufficient for the number of fields (prize pools, timings, team size, rules template). A dedicated page allows proper validation per step and a better editing experience.

---

### [v1.4] Platforms as Database-Driven Entity

**Baseline reference**: CMS module listed as "Games, Pages" — Platforms were not a separate entity.

**What changed**:
- `platforms` table added (migration added post-baseline).
- `CmsAdmin` now manages Platforms via CRUD alongside Games and Pages.
- Platform selection is mandatory when creating a tournament.

**Why**: Platform (PS5, PC, Mobile, etc.) needed to be admin-configurable without code changes.

---

### [v1.14] TournamentStateMachine Rollbacks

**Baseline reference**: State Machines — transitions were originally one-directional only.

**What changed**:
- Added reverse transitions: Re-open Registration (from REGISTRATION_CLOSED), Re-open Check-in (from CHECKIN_CLOSED).
- `guardCanCloseCheckin` validates minimum participant count before allowing check-in closure.

**Why**: Real-world tournament management requires flexibility when registration numbers are low or timing needs adjustment. Strict one-way transitions caused operational dead-ends.

---

### [v1.21] File Storage Scoped to Local (Temporary)

**Baseline reference**: File storage referenced R2/S3 for production uploads.

**What changed**:
- Dispute evidence and match result proof uploads use the local `public` disk instead of R2.
- File types restricted to images only (PNG, JPG, WEBP), max 2MB.

**Why**: Removed dependency on `league/flysystem-aws-s3-v3` which caused a missing class error in deployment. Deferred to post-MVP when R2 credentials are configured.

**Action required before full production**: See `project_progress.md` → Deployment Considerations for migration steps back to R2.

---

### [v1.26] Withdrawal Debit Architecture (Async via Listener)

**Baseline reference**: WalletService handles debits — originally implied synchronous debit inside `ProcessWithdrawalAction`.

**What changed**:
- Wallet debit does NOT happen in `ProcessWithdrawalAction`.
- Debit happens asynchronously: `ApproveWithdrawalAction` → dispatches `WithdrawalApproved` → `CreateLedgerEntryListener` (queued, `wallet` queue) → `WalletService::debit()`.
- `ProcessWithdrawalAction` only stamps `processed_at` and transitions APPROVED → PROCESSED.
- Idempotency guard: `CreateLedgerEntryListener` checks for existing `LedgerEntry` with matching `reference_type + reference_id` before debiting to prevent double-debit on queue retries.

**Why**: Queue retries on `ProcessWithdrawalAction` were causing double debits. Separating the debit into a listener with an idempotency check makes the flow retry-safe.

---

### [v1.27] InvitationStateMachine Wired to Actions

**Baseline reference**: Team module — `InvitationStateMachine` existed but actions directly mutated status.

**What changed**:
- `AcceptTeamInvitationAction`, `DeclineTeamInvitationAction`, and `RevokeTeamInvitationAction` now use `InvitationStateMachine` for all state transitions.

**Why**: State machine was bypassed, defeating the purpose of having it. Alignment ensures all guards and transition rules are enforced consistently.

---

### [v1.28] ResolveDisputeAction Authorization

**Baseline reference**: Actions/Services — business logic encapsulated in Actions with authorization.

**What changed**:
- `ResolveDisputeAction` signature changed from `int $resolvedByAdminUserId` to `User $actor`.
- Added explicit `hasAnyRole(['ADMIN', 'SUPER_ADMIN'])` role guard inside the action itself (was previously only checked at the component level).

**Why**: The action was the only one in the codebase without an internal authorization check. Calling it outside `MatchAdmin` (e.g., from a job or test) had no gate. Role guard inside the action enforces the principle that Actions are self-authorizing.

---

### [v1.28] Staff Activity Dashboard Added

**Baseline reference**: Admin Panel — original baseline did not include staff monitoring.

**What changed**:
- New Livewire component `StaffActivityDashboard` at `/admin/staff-activity`.
- Queries `activity_log` (Spatie) to show per-staff action breakdown filtered by date range and username.
- Restricted to `ADMIN` and `SUPER_ADMIN`.

**Why**: Audit visibility for super-admins to monitor staff behavior without raw DB access.

---

## 🛠️ Pending Implementation (Post-MVP Checklist)

*Refer to [PlayerSaloons_Execution_Checklist_v1.md] for detailed implementation tasks.*
