# PlayerSaloons — Architecture Baseline

**Last Updated**: 2026-06-14

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
- **Two-Way Confirmation (Pending)**: Planning integration of `ConfirmMatchResultAction` for match finalization and `AutoForfeitJob` for fallback resolution.

---

## 🛠️ Pending Implementation (Post-MVP Checklist)

*Refer to [PlayerSaloons_Execution_Checklist_v1.md] for detailed implementation tasks for Head-to-Head production realization.*
