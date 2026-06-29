# PlayerSaloons — Execution Checklist (Post-MVP)

**Status**: Active Backlog | **Last Updated**: 2026-06-29 (v1.68)

> **How to use this file**: When a bug, enhancement, or new feature is identified, add it here immediately under the correct section. When built, check the box and add a `## ✅` entry to `project_progress.md`. See `ONBOARDING.md` → Tracking Features, Bugs & Enhancements for the full sync guide.

---

## 🐛 Known Bugs

*None currently tracked. Add here as discovered.*

---

## 🎯 Head-to-Head (H2H) Production Realization

### Database Schema
- [x] Create `head_to_head_challenges` table (`id`, `user_id`, `game_id`, `stake_amount`, `status`, `created_at`). *(done v1.39)*
- [x] Create `head_to_head_matches` table (linking two players, outcome, stake resolution). *(done v1.39)*

### Matchmaking Engine
- [x] Implement `MatchmakerService` for querying waiting challenges. *(done v1.39)*
- [ ] Implement ELO/Skill Level matching (optional for v1).
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
- [ ] `test_elimination_modal_shows_on_lost_match` — lost player navigating to Matches tab triggers modal.
- [ ] `test_elimination_modal_does_not_show_if_not_lost` — active player, no modal.
- [ ] `test_elimination_modal_go_back_resets_tab` — "Go Back" reverts to Overview tab.
- [ ] `test_elimination_modal_continue_stays_on_matches` — "Continue" closes modal, stays on Matches.
- [ ] `test_stats_banner_calculation` — Win/Loss/Active counts match DB aggregates.
- [ ] `test_elimination_shifts_tournament_to_history` — lost player's tournament moves to History tab.
- [ ] `test_n_plus_one_query_prevention` — matches pre-fetched in single query (not per-tournament loop).
- [ ] `test_player_tournament_list_filtering` — Search, Game, Status, Frequency filters work.
- [x] `test_head_to_head_matchmaking_simulation` — H2H challenge creation, stake lock, matching, and payout. *(done v1.39)*

---

## 🛠️ Other Post-MVP Tasks

### File Storage Migration (Required Before Full Production)
- [ ] `composer require league/flysystem-aws-s3-v3`
- [ ] Set R2 env vars: `R2_ACCESS_KEY_ID`, `R2_SECRET_ACCESS_KEY`, `R2_BUCKET`, `R2_ENDPOINT`, `R2_PUBLIC_URL`
- [ ] `SubmitEvidenceAction` — change disk from `public` → `r2`
- [ ] `SubmitMatchResultAction` — change disk from `public` → `r2`
- [ ] Update file URL helpers from `/storage/{{ $path }}` → `Storage::disk('r2')->url($path)`

### CMS & Content
- [ ] CMS Module — Blog/News pages
- [ ] Translation Management Panel
- [x] Notification Broadcast Panel (UI for `broadcast_messages` table — schema exists, UI missing) *(done v1.35)*
- [x] Player notification bell UI — database-backed dropdown with realtime refresh *(done v1.36)*

### Compliance & User Management
- [ ] Compliance/Blacklisting (Middleware + Admin Page)
- [ ] Contact Inquiries (Admin Page)
- [ ] Newsletter Management (Admin Page)

### Identity
- [ ] Referral System Logic — integer ref ID is in DB but reward logic not implemented
- [ ] 2FA — schema has `two_factor_secret` / `two_factor_recovery_codes` but no UI/Action
- [x] `last_login_at` update on successful login (column exists, now updated in `Login.php` — v1.29)
- [x] `UserKycSubmitted` listener — event dispatched but no listener registered yet *(done v1.31 — `NotifyAdminsOfKycSubmissionListener`)*

### Financial
- [ ] External Payout Integration — `PROCESSED` state is currently manual; no PayPal/Stripe Connect
- [ ] `deposits.fee_amount` — field exists in DB and `$fillable`, but fee deduction not yet implemented
