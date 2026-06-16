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

## 🗺️ Detailed User Flows
For step-by-step walkthroughs and file mappings for specific user journeys, refer to the `/documentation` directory (local only):
- [Identity & Onboarding](documentation/01_identity_onboarding.md)
- [Tournament Lifecycle](documentation/02_tournament_lifecycle.md)
- [Financial Operations](documentation/03_financial_operations.md)
- [Team Management](documentation/04_team_management.md)
- [Admin & Operations](documentation/05_admin_operations.md)

---

## 📅 Implementation Phases

### Phase 1: Migrations & Seeders
Defines the foundational database schema and initial system state across all 8 domains.
- **Files**: `database/migrations/*.php`, `database/seeders/*.php`.

### Phase 2: Eloquent Models
Implements the domain layer with strict typing and integrity hooks.
- **Files**: `app/Modules/*/Models/*.php` (32 models).

### Phase 3: Laravel Enums
Standardizes statuses and types across the application.
- **Files**: `app/Shared/Enums/*.php` (14 backed enums).

### Phase 4: State Machines
The core engine for workflow-driven entities.
- **Files**: `app/Modules/*/StateMachines/*.php` (7 state machines).

### Phase 5: Domain Events
Thin, immutable events for decoupling modules.
- **Files**: `app/Modules/*/Events/*.php` (36 events).

### Phase 6: Identity Module
Handles user lifecycle, profiles, and regulatory compliance.
- **Files**: `app/Modules/Identity/Actions/`, `app/Modules/Identity/Models/User.php`.

### Phase 7: Wallet Service
A high-integrity ledger-based financial system.
- **Files**: `app/Modules/Wallet/Services/WalletService.php`, `app/Modules/Wallet/Actions/`.

### Phase 8: Tournament Module
Manages the competitive lifecycle and bracket orchestration.
- **Files**: `app/Modules/Tournament/Actions/`, `app/Modules/Tournament/Services/BracketGenerationService.php`.

### Phase 9: Match Module
Executes match-level logic, results, and disputes.
- **Files**: `app/Modules/Match/Actions/`, `app/Modules/Match/Listeners/AdvanceWinnerListener.php`.

### Phase 10: Team Module
Provides social and competitive grouping features.
- **Files**: `app/Modules/Team/Actions/`, `app/Modules/Team/Jobs/ExpireTeamInvitationsJob.php`.

### Phase 11: Scheduler Automation
Background automation for platform-wide time-sensitive tasks.
- **Files**: `routes/console.php`, `app/Modules/Tournament/Jobs/`.

### Phase 12: Notifications & Realtime
Real-time user engagement and platform updates via Laravel Reverb.
- **Files**: `app/Modules/Community/Services/NotificationService.php`, `app/Modules/Community/Broadcasting/`.

### Phase 13: Authorization (RBAC)
Granular, policy-based access control.
- **Files**: `app/Modules/*/Policies/`, `app/Providers/AppServiceProvider.php`.

### Phase 14: API Layer
Public and authenticated RESTful interface.
- **Files**: `app/Http/Controllers/Api/V1/`, `app/Http/Resources/`.

### Phase 15: Livewire UI (Frontend & Dashboard)
Modern, high-fidelity user interface using Tailwind CSS v4 and Lucide Icons.
- **Files**: `app/Livewire/Dashboard/`, `app/Livewire/Profile/`, `resources/views/welcome.blade.php`.

### Phase 16: Admin Panel (Livewire)
Operational command center for staff.
- **Files**: `app/Livewire/Admin/`.
- **Recent Updates (v1.22-v1.23)**:
    - **Dispute Evidence UI**: Enhanced admin view for dispute resolution with image thumbnails.
    - **Match Confirmation Logic**: Fixed `confirmResult` flow and winner advancement automation.
    - **Prize Pool Retention**: Optimized `CloseRegistrationAction` to retain guaranteed prize pools.

---

## 🚀 Recent Key Enhancements (from Project Progress)

### 🏆 Advanced Tournament Logic (v1.14 - v1.23)
- **State Rollbacks**: Allows admins to re-open registration or check-in.
- **Bracket Automation**: `BracketGenerationService` refactored for safety and "bye" support.
- **Auto-Start Matches**: Matches now transition to `IN_PROGRESS` automatically when players advance or tournaments start.

### 🛡️ Enhanced Dispute & Match Flow
- **Image-Only Evidence**: Restricted uploads to images (PNG/JPG/WEBP) with 2MB caps for stability.
- **Detailed Evidence UI**: Rich admin interface for reviewing player-submitted proof.

### 📊 Dashboard & UI Evolution
- **Cockpit Overview**: Redesigned player dashboard with widgets and real-time stats.
- **Persistent Tabs**: Using Alpine.js and `localStorage` to keep user tab state across refreshes.
- **Elimination Warnings**: Visual feedback for players who have been knocked out of tournaments.

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
| Icons | Lucide Icons |
