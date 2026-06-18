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
The following tests are identified in `project_progress.md` as necessary for full coverage:

*   **Feature Tests**:
    *   `test_join_tournament_button_is_restricted_by_role`: Verify only 'PLAYER' role can see/click join.
    *   `test_tournament_listing_filters_by_status`: Ensure player-side list correctly excludes Draft/Cancelled/Completed statuses.
    *   `test_view_restricted_details_policy`: Verify Matches and Activity tabs are hidden from non-participants.
    *   `test_frequency_tabs_functionality`: Verify Daily/Weekly/Monthly filtering in both admin and player lists.
*   **Component Tests (Livewire/Alpine)**:
    *   **Elimination Modal**:
        *   `test_elimination_modal_shows_on_lost_match`: Player has lost -> navigating to Matches tab triggers warning.
        *   `test_elimination_modal_does_not_show_if_not_lost`: Player active -> no warning.
        *   `test_elimination_modal_actions`: "Go Back" resets tab; "Continue" stays on tab.
    *   **My Tournaments UI**:
        *   `test_stats_banner_calculation`: Verify Win/Loss/Active counts are accurate.
        *   `test_elimination_shifts_tournament_to_history`: Verify UI updates location of tournament after a loss.
        *   `test_n_plus_one_query_prevention`: Ensure matches are pre-fetched in a single query.
    *   **Discovery**:
        *   `test_player_tournament_list_filtering`: Verify search, game, and status filters.

## 🛠️ Feature Gaps & Unused Schema
*   **Missing Features**:
    *   **Auto-Forfeit Logic**: `AutoForfeitJob` exists but the specific timeout configuration (e.g., "forfeit after 15 mins of inactivity") needs to be exposed in `SystemSettings`.
    *   **Rematch Voting**: Flow for players to request a rematch before a dispute is filed.
    *   **Streaming Integration**: `twitch_stream_url` and `youtube_stream_url` in schema but no live integration yet.
*   **Unused Schema Columns**:
    *   `tournaments.metadata`: JSON field for extended rules (e.g., "No items", "Final Destination only") not yet processed by the wizard.
    *   `matches.server_id`: Field for external game server integration (e.g., CS2/Dota2) currently null.
    *   `tournament_registrations.team_id`: Placeholder for team-based tournaments (currently focusing on solo).
