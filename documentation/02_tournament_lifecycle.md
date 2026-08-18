# User Flow: Tournament Lifecycle (Player)

This document describes the player's journey through a tournament, from discovery to match resolution.

## 1. Discovery
Players browse active and upcoming tournaments.

*   **Action**: Player navigates to the tournament list or search.
*   **UI Component**: `app/Livewire/Tournament/TournamentList.php`
*   **View**: `resources/views/livewire/tournament/tournament-list.blade.php`
*   **Connected Files**:
    *   `app/Modules/Tournament/Models/Tournament.php`: The primary tournament model.
    *   `app/Modules/CMS/Models/Game.php`: Linked game metadata (banner, name).

## 2. Registration
Players join a tournament and pay the entry fee if applicable.

*   **Action**: Player clicks "Join" on a tournament detail page.
*   **UI Component**: `app/Livewire/Tournament/TournamentDetail.php`
*   **Logic (Actions)**:
    *   `app/Modules/Tournament/Actions/RegisterForTournamentAction.php`: Atomically reserves a seat and handles fee collection.
*   **Connected Files**:
    *   `app/Modules/Tournament/Models/TournamentRegistration.php`: Tracks registration status.
    *   `app/Modules/Wallet/Services/WalletService.php`: Deducts entry fees from the player's wallet.
    *   `app/Modules/Tournament/Events/TournamentSeatReserved.php`: Dispatched upon registration.

## 3. Check-in
Mandatory step before the tournament starts to confirm participation.

*   **Action**: Player clicks "Check-in" within the check-in window.
*   **UI Component**: `app/Livewire/Dashboard/PlayerDashboard.php` or `TournamentDetail.php`
*   **Logic (Actions)**:
    *   `app/Modules/Tournament/Actions/CheckinParticipantAction.php`: Creates an immutable check-in record.
*   **Connected Files**:
    *   `app/Modules/Tournament/Models/TournamentCheckin.php`: The immutable check-in log.
    *   `app/Modules/Tournament/Events/PlayerCheckedIn.php`: Dispatched upon successful check-in.

## 4. Match Play
Executing the competitive matches within the bracket.

*   **Action**: Player enters their assigned match lobby.
*   **UI Component**: `app/Livewire/Match/MatchDetail.php`
*   **Connected Files**:
    *   `app/Modules/Match/Models/GameMatch.php`: Model representing a specific match (table: `matches`).
    *   `app/Modules/Match/StateMachines/MatchStateMachine.php`: Governs match lifecycle (PENDING → READY → IN_PROGRESS → WAITING_FOR_CONFIRMATION → COMPLETED).
    *   `app/Modules/Match/Listeners/AutoStartMatchesListener.php`: Automatically transitions READY matches to IN_PROGRESS when a tournament starts or a player advances — no manual `StartMatchAction` call needed.

## 5. Submit Result
Reporting the outcome of a match.

*   **Action**: Player uploads a screenshot of the victory/result and submits.
*   **UI Component**: `app/Livewire/Match/MatchDetail.php`
*   **Logic (Actions)**:
    *   `app/Modules/Match/Actions/SubmitMatchResultAction.php`: Records the reported score and proof. Transitions match to `WAITING_FOR_CONFIRMATION`.
    *   `app/Modules/Match/Actions/ConfirmMatchResultAction.php`: Opponent confirms the result. Must be confirmed by the **opponent** (not the submitter). Transitions match to `COMPLETED` and dispatches `MatchCompleted`.
*   **Connected Files**:
    *   `app/Modules/Match/Models/MatchResultSubmission.php`: Stores submitted results and proof paths.
    *   `app/Modules/Match/Listeners/AdvanceWinnerListener.php`: Automates bracket progression on `MatchCompleted`.
    *   `app/Modules/Match/Events/MatchResultSubmitted.php`.
*   **Timeout**: If the opponent does not confirm within the tournament's `waiting_result_time` window, `AutoForfeitJob` auto-resolves the match.
*   **Legacy note**: `RESULT_SUBMITTED` exists only for older rows and compatibility. New submissions use `WAITING_FOR_CONFIRMATION`.

## 6. Open Dispute
Resolving conflicts when players disagree on results.

*   **Action**: Player flags a match for manual review and uploads evidence.
*   **UI Component**: `app/Livewire/Match/MatchDetail.php`
*   **Logic (Actions)**:
    *   `app/Modules/Match/Actions/OpenDisputeAction.php`: Creates a dispute record and halts automated progression.
    *   `app/Modules/Match/Actions/SubmitEvidenceAction.php`: Handles evidence file uploads.
*   **Connected Files**:
    *   `app/Modules/Match/Models/MatchEvidence.php`: Immutable evidence records.
    *   `app/Modules/Match/Events/MatchDisputed.php`.

## 7. Head-to-Head Competitions and Legacy Wagers
New head-to-head competitions are platform-created tournament occurrences with a fixed 1v1 shape.

*   **Current action**: Staff selects `Head-to-Head (1v1)` in `/admin/tournaments/create`; players discover and join it through the normal competition browse/detail flow.
*   **Invariant**: Both action and template layers force `min_participants = 2`, `max_participants = 2`, and `team_size = 1`.
*   **Legacy wager feature**: The player-created wager domain is retained for future use but disabled by default with `PLAYER_WAGER_ENABLED=false`. When disabled, its route, navigation, global prompt, and expiry job are not registered/rendered.
*   **UI Component**: `app/Livewire/Match/HeadToHeadList.php`
*   **Legacy UX Surface**: When the feature flag is deliberately enabled, `/head-to-head` exposes the preserved initiate/open/active/history wager flow.
*   **Logic (Actions/Services)**:
    *   `app/Modules/Match/Actions/CreateHeadToHeadChallengeAction.php`: Creates a waiting challenge and locks creator stake. It blocks another waiting challenge or active duel by the same player for the same game.
    *   `app/Modules/Match/Services/HeadToHeadMatchmakerService.php`: Finds compatible waiting challenges by game, stake, platform, and region.
    *   `app/Modules/Match/Actions/AcceptHeadToHeadChallengeAction.php`: Locks opponent stake and creates an in-progress H2H match. It rejects accepts for a different selected game and blocks accepting if the player already has a waiting challenge or active duel for that game.
    *   `app/Modules/Match/Actions/SubmitHeadToHeadResultAction.php`: Records submitted winner, notes, optional proof screenshot, and moves the H2H match to `WAITING_FOR_CONFIRMATION`.
    *   `app/Modules/Match/Actions/ConfirmHeadToHeadResultAction.php`: Opponent confirms and releases both locked stakes to the winner.
    *   `app/Modules/Match/Actions/DisputeHeadToHeadResultAction.php`: Marks the result disputed and stores optional dispute notes/proof.
    *   `app/Modules/Match/Actions/ResolveHeadToHeadDisputeAction.php`: Admin awards creator, awards opponent, or voids/refunds both stakes from `/admin/matches`.
    *   `app/Modules/Match/Jobs/ExpireHeadToHeadMatchesJob.php`: Runs every minute. Expired waiting challenges refund the creator; stale `IN_PROGRESS` or `WAITING_FOR_CONFIRMATION` matches escalate to `DISPUTED` for admin review.
*   **Connected Files**:
    *   `app/Modules/Match/Models/HeadToHeadChallenge.php`
    *   `app/Modules/Match/Models/HeadToHeadMatch.php`
    *   `app/Livewire/Match/HeadToHeadDuelPrompt.php`: Retained legacy prompt; it is feature-gated and no longer globally polls.
    *   `app/Modules/Match/StateMachines/HeadToHeadMatchStateMachine.php`
    *   `app/Shared/Enums/HeadToHeadDisputeResolution.php`
    *   `app/Shared/Enums/HeadToHeadChallengeStatus.php`
    *   `app/Shared/Enums/HeadToHeadMatchStatus.php`
*   **Fair-play rule**: H2H does not auto-award wins from an unconfirmed claim or stale timer. Confirmation releases payout; timeout/dispute cases keep stakes locked until an admin awards a player or voids/refunds the duel. Players can only have one waiting challenge or active duel per game at a time.
*   **Timeout outcomes**:
    *   Waiting challenge past `expires_at`: `EXPIRED` and creator stake refunded.
    *   In-progress match past `match_timer_minutes + 15` minutes: `DISPUTED` with system timeout note.
    *   Submitted result past `confirmation_due_at`: `DISPUTED` with system timeout note.
*   **Settlement**: A confirmed or admin-awarded winner receives both stakes less the admin-configured `h2h.commission_percentage` (10% default). `ResolveHeadToHeadStakeAction` records one idempotent `H2H_PAYOUT` ledger credit for the net amount.

## 8. Scheduled Tournament Automation
Tournament maintenance is registered in `routes/console.php` and runs through the production scheduler container.

*   **Lifecycle reconciliation**: `tournaments:reconcile-lifecycle` runs every minute and catches overdue competitions up through registration, check-in, bracket, and start transitions. Opted-in underfilled competitions use the same cancellation/refund action.
*   **Recurring generation**: `tournaments:auto-generate` runs every five minutes. Timezone-aware daily, weekly, or monthly templates are row-locked, capped per run, and protected by a unique template/start occurrence key.
*   **Scheduling safety**: Lifecycle and generation commands use overlap protection and a single-server lock. Production must run both the scheduler and queue worker.
*   **Admin visibility**: `/admin/tournaments/{id}/matches` opens `TournamentMatches`, a fixtures-style tournament match view with status filters and individual or team identity.

## 🧪 Isolated Test Cases
### 1. Registration & Wallet
*   **Success**: `test_player_can_register_for_tournament_with_sufficient_balance`
    *   Assert `tournament_registrations` entry created.
    *   Assert wallet balance is deducted by entry fee.
    *   Assert `LedgerEntry` of type `DEBIT` created.
*   **Failure**: `test_registration_fails_if_balance_is_insufficient`
*   **Limit**: `test_registration_fails_if_tournament_is_full`

### 2. Match Progression
*   **Success**: `test_match_advances_winner_automatically_after_confirmation`
    *   Submit result for Match A.
    *   Confirm result for Match A.
    *   Assert winner is populated in next round's Match B.
*   **Dispute**: `test_match_locks_on_dispute_and_prevents_auto_advancement`

### 📋 Pending Tests (Testing Debt)
The role, listing, restricted-detail, elimination, tournament-stat, history, N+1, and player-filter tests were completed in v1.32/v1.98. Remaining frequency-filter coverage is tracked in `execution_checklist.md`.

## 🛠️ Feature Gaps & Unused Schema
*   **Missing Features**:
    *   None currently tracked in this document.
*   **Provider Live Status Detection (v1.99)**:
    *   `ProviderLiveStatusService` checks YouTube, Twitch, and Facebook when provider credentials are configured.
    *   `RefreshProviderLiveStatusesJob` runs every two minutes for public, non-taken-down channels.
    *   Successful checks update `stream_channels.is_live` and `provider_status`; unavailable credentials or API errors preserve the previous manual live state.
    *   Required optional environment keys: `YOUTUBE_API_KEY`, `TWITCH_CLIENT_ID`, `TWITCH_ACCESS_TOKEN`, and `FACEBOOK_ACCESS_TOKEN`.
*   **Unused Schema Columns**:
    *   `tournaments.metadata`: JSON field for extended rules (e.g., "No items", "Final Destination only") not yet processed by the wizard.
    *   `matches.server_id`: Field for external game server integration (e.g., CS2/Dota2) currently null.
