# GamersRival (PlayerSaloons) — Roadmap & Enhancements Checklist

Date: 2026-11-26  
Status: **Aligned via Design Interview — Ready for Phased Implementation**

> This document records the finalized architectural decisions and implementation checklist for upcoming enhancements and new features. Items marked `[ ]` have not been implemented yet.

---

## 1. Completed Baseline (Shipped on `docs/new-system-requirements`)

- [x] Game Categories (`Video Games`, `Card & Board Games`), visibility rules, category tabs, and game search
- [x] PlayStrategy integration (Personal OAuth2 token linking, automated board creation, live auto-polling, draw rematches, aborted-match dispute handling, 3D card covers, Admin PlayStrategy quick-fill catalog)
- [x] Google Authentication (OAuth 2.0 + PKCE, compliance sign-up completion, 2FA/ban/soft-delete safeguards, profile connect/disconnect)
- [x] Docker startup migration & idempotent seeding (`GameCategorySeeder`, `PlayStrategyGamesSeeder`)

---

## 2. Agreed Delivery Order

1. **Phase A — Enhancements (Classic UI Polish & Admin Deposits):** Light/Dark theme contrast + Desktop overflow fixes, and dedicated Admin Deposits History page.
2. **Phase B — Find Tournament:** Guided Quick-Finder modal/bar across Homepage hero, Player Dashboard, and Topbar.
3. **Phase C — AI-Assisted Dispute Management:** Multimodal Vision AI (Gemini behind swappable interface), starting in Advisory/Shadow Mode with tiered `>=95%` auto-resolve guardrails.
4. **Phase D — Simplified GamersRival UI (Separate Next.js Repo + `/api/v2/*`):** Decoupled Next.js App Router SPA with BFF HttpOnly Sanctum auth, phased rollout (Core Player Arena first, then Simplified Topbar Admin).
5. **Deferred — Optional Paid Membership / Subscription Pilot:** On hold pending pricing/business research.

---

## 3. Phase A — Enhancements (First Priority)

### 3.1 Light Mode, Dark Mode & Desktop Layout Polish
- [x] Audit and fix **Light Mode** color inconsistencies where text or icons become invisible or low-contrast
- [x] Audit and fix **Dark Mode** color inconsistencies where text or icons become hard to read
- [x] Fix **Desktop / Tablet layout overflow issues** (mobile layout is currently OK):
  - [x] Prevent text overflowing or clipping outside cards
  - [x] Fix homepage sections and card layouts breaking alignment or responsiveness on desktop widths
- [x] Standardize contrast tokens across guest, player, and admin screens

### 3.2 Admin Deposits History Page
- [x] Add a dedicated **Deposits** page in the Admin Panel (`/admin/deposits`)
- [x] Display complete deposit transaction history across all players:
  - [x] Player/user details, payment gateway (Stripe / Payop / etc.), gross amount, fee, net credited, status, external reference ID, and timestamps
  - [x] Search, status/gateway/date-range filters, summary totals, and pagination
  - [x] Read-only financial audit visibility preserving existing wallet/ledger integrity

---

## 4. Phase B — Find Tournament (Input-Based Active Tournament Finder)

### Agreed Design Decisions
- **UX Pattern:** Guided Quick-Finder modal/bar — players pick **Game / Category**, **Platform**, **Entry Fee range / Free**, and **Time Window**, and immediately see a ranked list of open tournaments with **1-click Join**.
- **Entry Points:** Accessible from **Homepage hero**, **Player Dashboard**, and **Topbar** (`Find Tournament` CTA).
- **Zero Exact Matches Fallback:** Show closest active tournaments (same game or category) with a clear notice that no exact match was found.
- **Catalog Integrity:** Strictly exclude inactive categories/games, soft-deleted games, and closed occurrences (both Legacy and V2).

### Implementation Checklist
- [x] Shared `FindActiveTournamentsService` (ranked exact matches + closest fallback query, reusable by Livewire and future `/api/v2/*`)
- [x] Quick-Finder Livewire modal/component wired into Homepage hero, Player Dashboard, and Topbar
- [x] Feature tests covering exact matches, fallback matches, inactive category exclusion, and 1-click Join

---

## 5. Phase C — AI-Assisted Dispute Management

### Agreed Design Decisions
- **AI Provider:** **Gemini (Google AI API — multimodal vision)** behind a swappable `DisputeAiAnalyzerInterface` (easy to swap to OpenAI/Anthropic later).
- **Trigger Timing:** Trigger async queued analysis **immediately when the 1st player uploads evidence**, and **re-run** if the 2nd player uploads evidence later.
- **Autonomy & Guardrails (`>=95%` Auto-Resolve):**
  - **Advisory runs immediately** on 1st upload (and refreshes on 2nd upload), showing suggested ruling, confidence score, and evidence checklist in the Admin Dispute Modal.
  - **Auto-resolve NEVER fires on 1st upload alone** — it only executes once **both players have uploaded evidence** OR after a configurable **evidence response window** expires.
  - **Tiered financial & safety caps:** Auto-resolve ONLY when:
    - Confidence `>= 95%` (configurable)
    - Match prize/entry value is **below a configurable cap** (e.g. `<= €50`)
    - Match is **NOT a tournament final**
    - **No dishonesty strike or ban** is issued automatically (strikes/bans remain Admin-only)
  - **Rollout Gate:** Admin toggle in System Settings — **starts in Advisory / Shadow Mode** first to validate accuracy before enabling live auto-resolve.
  - **Full Audit Trail:** Persist structured AI verdict, confidence, reasoning, prompt/evidence references, and whether auto-resolved or admin-overridden.

### Implementation Checklist
- [x] Migration: `dispute_ai_analyses` table (dispute ID, provider/model, status, suggested resolution, confidence, reasoning JSON, evidence snapshot, applied flag)
- [x] `GeminiDisputeAnalyzer` client (HTTP + structured JSON schema + image resize + prompt-injection hardening + timeout/error safety)
- [x] Async `AnalyzeDisputeWithAiJob` triggered on evidence upload + delayed `EvaluateAiDisputeAutoResolveJob` after response window
- [x] System Settings controls (enable AI analysis, Shadow/Advisory vs Auto-Resolve toggle, confidence threshold, max prize cap, response window minutes)
- [x] Admin Dispute Modal UI showing AI summary, confidence badge, evidence checklist, and 1-click re-run / override
- [x] Feature tests covering Shadow Mode, 1st-upload wait guard, prize-cap block, finals block, dishonesty-strike block, and `>=95%` eligible auto-resolve

---

## 6. Phase D — Simplified GamersRival UI (Next.js Edition + `/api/v2/*`)

### Agreed Design Decisions
- **Repository & Hosting:** Completely separate Git repository (**Next.js App Router**) deployed independently on Vercel/Coolify, while the existing Livewire/Blade app remains untouched as the **"Classic" version**.
- **Backend API:** Laravel exposes clean versioned Sanctum JSON REST endpoints under **`/api/v2/*`** (reusing existing Domain Actions, Policies, and State Machines — zero duplicated business logic).
- **Secure Auth & Realtime (BFF Pattern):**
  - Next.js Route Handlers act as a **Backend-for-Frontend (BFF)** storing the Sanctum Bearer token inside an **`HttpOnly`, `Secure`, `SameSite=Lax` cookie** (never exposed to `localStorage` / client XSS).
  - **Google OAuth:** Laravel handles Google OAuth callback and returns a **short-lived one-time exchange code** to Next.js, which exchanges it server-to-server for a Sanctum Bearer token.
  - **Realtime:** Laravel Reverb broadcasting auth handled via signed broadcasting auth endpoint.
- **UI Philosophy:** Modern, low-complexity, **topbar-only navigation** (no heavy sidebar — including simplified Admin Panel), streamlined submission flows ("submit lang").
- **Phased Rollout:**
  - **Sub-Phase D1 — Core Player Arena:** Auth + Google OAuth, Home + Find Tournament, Join Tournament, Match Room + PlayStrategy + Quick Submit, Wallet, Profile.
  - **Sub-Phase D2 — Simplified Topbar Admin:** Tournaments, Matches + AI Disputes, Deposits/Withdrawals, Games/Categories, Users. *(Rarely-used admin modules — CMS, Translations, Audit Logs, Policies — remain on Classic Admin until needed.)*

### Implementation Checklist
- [ ] Laravel `/api/v2/*` Auth endpoints (login, register, 2FA challenge, Google OAuth one-time code exchange, logout, `/me`, Reverb auth)
- [ ] Laravel `/api/v2/*` Player Arena endpoints (tournaments, Find Tournament, register/cancel, matches, PlayStrategy, wallet, profile)
- [ ] Laravel `/api/v2/*` Simplified Admin endpoints (tournaments, matches, AI disputes, deposits/withdrawals, games/categories, users)
- [ ] Separate Next.js App Router repository scaffold (BFF auth layer, topbar shell, Player Arena -> Simplified Admin)

---

## 7. Deferred Items & Future Enhancements (Not Started Yet)

- [ ] **Homepage / Player Dashboard "What's New" Announcement Popup Modal** *(Noted — Upcoming Feature)*
  - [ ] Dismissible announcement / news popup modal on Homepage and/or Player Dashboard for partnerships, platform updates, and news (supporting rich text, image banner, or embedded/uploaded video)
  - [ ] **Recommended Admin Management:** Extend existing **Admin → Broadcast Notifications** (`/admin/broadcasts`, `BroadcastNotificationAdmin` / `BroadcastMessage`) with display type (`banner` vs `popup_modal`), target surface (`homepage`, `player_dashboard`, `both`), optional video/image media + CTA link, active date window (`starts_at` / `ends_at`), and per-announcement dismiss tracking so users only see new updates once
- [ ] **Optional Paid Membership / Subscription Pilot** *(Deferred)*
  - [ ] Research EUR pricing, legal/billing structure, 30-day validity, and operating-cost economics before finalizing scope
  - [ ] Revisit `documentation/new-requirements-2026-11.md` Section 4 once research is complete
