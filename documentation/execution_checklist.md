# PlayerSaloons — Execution Checklist (Post-MVP)

**Status**: Active Backlog | **Last Updated**: 2026-07-12 (v1.110)

> **How to use this file**: When a bug, enhancement, or new feature is identified, add it here immediately under the correct section. When built, check the box and add a `## ✅` entry to `project_progress.md`. See `ONBOARDING.md` → Tracking Features, Bugs & Enhancements for the full sync guide.

---

## 🐛 Known Bugs

*None currently tracked. Add here as discovered.*

### Recently Fixed
- [x] Livewire/Reverb requests could send `X-Socket-ID: undefined`, causing `Invalid socket ID undefined` 500 errors in global/stream chat broadcasts *(fixed v1.97)*
- [x] `/chat` global message send failed when Reverb/Pusher was unavailable; messages now persist and broadcast failures are logged *(fixed v1.96)*

---

## 🎯 Head-to-Head (H2H) Production Realization

### Database Schema
- [x] Create `head_to_head_challenges` table (`id`, `user_id`, `game_id`, `stake_amount`, `status`, `created_at`). *(done v1.39)*
- [x] Create `head_to_head_matches` table (linking two players, outcome, stake resolution). *(done v1.39)*

### Matchmaking Engine
- [x] Implement `MatchmakerService` for querying waiting challenges. *(done v1.39)*
- [x] Implement ELO/Skill Level matching — per-game ratings, idempotent result updates, and widening skill window. *(done v1.99)*
- [x] Implement stake validation (check balance/lock amount in wallet). *(done v1.39)*
- [x] Add H2H proof upload and admin dispute review flow. *(done v1.40)*
- [x] Add H2H timeout/auto-expiry policy. *(done v1.43)*

### Tournament & Match Lifecycle Automation
- [x] Implement `AutoStartMatchesListener` — transitions READY matches to IN_PROGRESS when tournament starts.
- [x] Update `AdvanceWinnerListener` — auto-starts matches for subsequent rounds.
- [x] Create `tournaments:start-matches` artisan command for backfilling stuck matches.
- [x] Implement `ConfirmMatchResultAction` — two-way confirmation before match completes.
- [x] Develop `AutoForfeitJob` — uses `tournament.waiting_result_time` to auto-resolve `WAITING_FOR_CONFIRMATION` matches.
- [x] Update `MatchStateMachine` — added `WAITING_FOR_CONFIRMATION` transition.
- [x] Register `AutoForfeitJob` in Scheduler.
- [x] Unit tests for confirmation flow (`ConfirmResultFlowTest`) and forfeit timeouts (`AutoForfeitJob`). *(v1.23)*

### Infrastructure (Production Deployment)
- [x] Scheduler runs via dedicated `scheduler` container in `docker-compose.prod.yml`.
- [x] Queue worker runs via dedicated `worker` container (Horizon) in `docker-compose.prod.yml`.
- [x] Verify Horizon dashboard is accessible and workers are processing queues in production. *(Status: Active, confirmed v1.29)*

### Escrow/Wallet Integration
- [x] Develop `LockStakeAction` — reserve funds during H2H queueing. *(done v1.39)*
- [x] Develop `ResolveStakeAction` — release funds to winner or refund on failure. *(done v1.39)*

---

## 🧪 Testing Debt

These tests are identified but not yet implemented. Priority order within each section.

### Manual UI QA
- [ ] Phase 2 guest/auth/contact/admin manual UI QA pass — landing, register, email verification, login, forgot password, contact page, player support link, and admin contact inbox. **Deferred**: automated coverage and focused fixes are in place; full browser walkthrough will be scheduled after the next Phase 2 feature priorities.
- [x] Desktop player sidebar bottom spacing on long pages — sidebar is viewport-fixed and the content pane reserves collapsed sidebar width *(fixed v1.94)*

### Tournament & Admin
- [ ] `test_admin_tournament_filter_persistence` — search/status filters survive page refresh.
- [ ] `test_admin_frequency_tab_functionality` — Daily/Weekly/Monthly filter in admin tournament list.
- [ ] `test_player_frequency_tab_functionality` — same filter on player-side browse.
- [x] `test_join_tournament_button_is_restricted_by_role` — only PLAYER role sees Join button *(done v1.32)*
- [x] `test_tournament_listing_filters_by_status` — player list excludes Draft/Cancelled/Completed *(done v1.32)*
- [x] `test_view_restricted_details_policy` — Matches/Activity tabs hidden from non-participants *(done v1.32)*
- [ ] `test_custom_pagination_rendering` — dark-neon pagination renders correctly.
- [ ] `test_admin_navigation_flow` — `wire:navigate` SPA transitions between list and create/edit.

### Livewire Component Tests
- [x] `test_elimination_modal_shows_on_lost_match` — lost player navigating to Matches tab triggers modal. *(done v1.98)*
- [x] `test_elimination_modal_does_not_show_if_not_lost` — active player, no modal. *(done v1.98)*
- [x] `test_elimination_modal_go_back_resets_tab` — "Go Back" reverts to Overview tab. *(done v1.98)*
- [x] `test_elimination_modal_continue_stays_on_matches` — "Continue" closes modal, stays on Matches. *(done v1.98)*
- [x] `test_stats_banner_calculation` — Win/Loss/Active counts match DB aggregates. *(done v1.98)*
- [x] `test_elimination_shifts_tournament_to_history` — lost player's tournament moves to History tab. *(done v1.98)*
- [x] `test_n_plus_one_query_prevention` — matches pre-fetched in single query (not per-tournament loop). *(done v1.98)*
- [x] `test_player_tournament_list_filtering` — Search, Game, Status, Frequency filters work. *(done v1.98)*
- [x] `test_head_to_head_matchmaking_simulation` — H2H challenge creation, stake lock, matching, and payout. *(done v1.39)*

---

## 🛠️ Other Post-MVP Tasks

### Ordered Feature Queue

Implement these next, in order:

1. [x] Contact Inquiry Workflow Polish — added status counters, reply-by-email shortcut, clearer category/status badges, and an admin dashboard inquiry summary. *(done v1.101)*
2. [x] Newsletter Management — added admin audience search, recorded campaign sending/delivery totals, campaign history, and signed unsubscribe handling. *(done v1.102)*
3. [x] Referral System Logic — added registration attribution, first-successful-deposit qualification, idempotent ledger credits, profile stats, and admin-adjustable reward settings. *(done v1.103; qualification updated v1.104)*
4. [x] Deposit Processing Fee — added dynamic fixed/percentage settings, player cost breakdown, Stripe total charging, persisted fees, and webhook consistency validation. *(done v1.105)*
5. [x] Advertisement & Promotion Management — added admin scheduling/CRUD, active player dashboard banners, dismiss controls, and click tracking. *(done v1.106)*
6. [x] Player Review Management — added one editable star review per player, moderation, and approved public landing testimonials. *(done v1.107)*

After the ordered feature queue:

7. [x] Team Tournaments — captain registration, active-roster validation and snapshotting, team bracket slots, roster access, team check-in, and team-aware match UI. *(done v1.110)*
8. [x] Auto-Forfeit Timeout Setting — System Settings now controls the default inherited by new tournaments while preserving per-tournament overrides. *(done v1.110)*
9. [x] Rematch Voting — either side can request a time-limited rematch and the opposing side can agree before a dispute; mutual agreement creates a replacement match without advancing the bracket. *(done v1.110)*

### File Storage Migration (Required Before Full Production)
- [ ] `composer require league/flysystem-aws-s3-v3`
- [ ] Set R2 env vars: `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_URL`
- [ ] `SubmitEvidenceAction` — change disk from `public` → `r2`
- [ ] `SubmitMatchResultAction` — change disk from `public` → `r2`
- [ ] Update file URL helpers from `/storage/{{ $path }}` → `Storage::disk('r2')->url($path)`

### Tournament Broadcasts
- [x] Streaming Integration — player-created YouTube, Twitch, and Facebook streams, admin takedown/restore moderation, tournament stream URL validation, and embedded `/streams` plus tournament detail playback *(done v1.91)*
- [x] Provider Live Status Detection — optional YouTube/Twitch/Facebook API polling for true live/offline state. *(done v1.99)*

### CMS & Content
- [x] CMS Module — Blog/News pages *(done v1.82)*
- [x] Translation Management Panel *(done v1.69)*
- [x] Notification Broadcast Panel (UI for `broadcast_messages` table — schema exists, UI missing) *(done v1.35)*
- [x] Player notification bell UI — database-backed dropdown with realtime refresh *(done v1.36)*

### Community & Chat
- [x] Global Chat — replace mock Livewire session messages with persisted `chat_conversations` / `chat_messages` and Reverb delivery *(done v1.95)*
- [x] Player-to-Player Chat — direct conversation creation by username, participant-only access, persisted messages, and private Reverb channel delivery *(done v1.95)*
- [x] Team Chat — active team member channels backed by the shared chat pipeline and private Reverb authorization *(done v1.95)*

### Compliance & User Management
- [x] Compliance/Blacklisting (Middleware + Admin Page) *(done v1.99)*
- [x] Contact Inquiries (Public/player form + Admin Page) *(done v1.78; resolve/archive UX tightened v1.81)*
- Ordered work is tracked under **Ordered Feature Queue** above.

### Identity
- Referral implementation is tracked under **Ordered Feature Queue** above.
- [x] 2FA — authenticator setup, login challenge, one-time recovery codes, and password-confirmed disable flow. *(done v1.99)*
- [x] `last_login_at` update on successful login (column exists, now updated in `Login.php` — v1.29)
- [x] `UserKycSubmitted` listener — event dispatched but no listener registered yet *(done v1.31 — `NotifyAdminsOfKycSubmissionListener`)*

### Financial
- Deposit fee implementation is tracked under **Ordered Feature Queue** above.

---

## 🚀 Production Readiness Checklist

These tasks are intentionally deferred while the application remains in testing:

- [ ] External Payout Provider Integration — replace the manual `PROCESSED` withdrawal workflow after the provider, supported regions, account onboarding, and compliance requirements are finalized.
- [ ] R2/S3 File Storage Migration — complete the file-storage steps above before accepting real users at scale.
