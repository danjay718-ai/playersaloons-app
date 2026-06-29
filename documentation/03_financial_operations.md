# User Flow: Financial Operations

This document details the ledger-based financial workflows for players.

## 1. Deposit
Adding funds to the player's wallet.

*   **Action**: Player enters an amount on `/wallet`, is redirected to Stripe Checkout, and completes a sandbox/test card payment.
*   **UI Component**: `app/Livewire/Wallet/WalletDashboard.php` with `resources/views/livewire/wallet/wallet-dashboard.blade.php`.
*   **Logic (Actions)**:
    *   `app/Modules/Wallet/Services/StripeCheckoutService.php`: Creates the Stripe Checkout Session with wallet/user metadata and success/cancel URLs.
    *   `app/Http/Controllers/StripeWebhookController.php`: Verifies Stripe webhook signatures and handles `checkout.session.completed`.
    *   `app/Modules/Wallet/Actions/ProcessDepositAction.php`: Atomically updates the wallet and creates a ledger entry.
*   **Connected Files**:
    *   `app/Modules/Wallet/Models/Deposit.php`: Tracks deposit status and external reference IDs.
    *   `app/Modules/Wallet/Services/WalletService.php`: Central service for credit/debit logic.
    *   `app/Modules/Wallet/Models/LedgerEntry.php`: The immutable source of truth for all transactions.
    *   `app/Modules/Wallet/Events/WalletCredited.php`.
*   **Webhook Route**: `POST /stripe/webhook` is CSRF-exempt and protected by Stripe signature verification (`STRIPE_WEBHOOK_SECRET`).
*   **Idempotency**: Stripe Checkout Session ID is stored as `deposits.provider_reference`; duplicate webhooks return the existing deposit and do not double-credit the wallet.

### Stripe Local Testing

Local Stripe webhooks use the Stripe CLI, not the Stripe Dashboard webhook endpoint:

```bash
stripe login
stripe listen --events checkout.session.completed,payment_intent.succeeded,payment_intent.payment_failed --forward-to localhost:8088/stripe/webhook
```

The CLI prints a local signing secret:

```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

Use Stripe test cards only, for example `4242 4242 4242 4242`.

### Stripe Staging / Coolify

Staging does not run `stripe listen`. Create a Stripe Dashboard webhook endpoint in test mode:

```text
https://<staging-domain>/stripe/webhook
```

Set these Coolify environment variables:

```env
APP_URL=https://<staging-domain>
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_from_staging_dashboard_endpoint
```

`STRIPE_KEY` / `STRIPE_SECRET` are also supported, but `STRIPE_PUBLIC_KEY` / `STRIPE_SECRET_KEY` match the current local environment.

## 2. Withdrawal Request
Cashing out funds from the platform.

*   **Action**: Player requests a withdrawal of their available balance.
*   **UI Component**: `app/Livewire/Wallet/WalletDashboard.php` for the player wallet page; `app/Http/Controllers/Api/V1/WalletApiController.php` for API withdrawals.
*   **Logic (Actions)**:
    *   `app/Modules/Wallet/Actions/RequestWithdrawalAction.php`: Validates balance, KYC status, and creates a pending withdrawal.
*   **Connected Files**:
    *   `app/Modules/Wallet/Models/Withdrawal.php`: Tracks the lifecycle of the withdrawal request.
    *   `app/Modules/Wallet/StateMachines/WithdrawalStateMachine.php`: Governs transitions (PENDING -> UNDER_REVIEW -> APPROVED -> PROCESSED).
    *   `app/Modules/Wallet/Policies/WithdrawalPolicy.php`: Enforces security rules (e.g., KYC required).
    *   `app/Modules/Wallet/Events/WithdrawalRequested.php`.

## 3. Balance View
Real-time monitoring of wallet funds and history.

*   **Action**: Player views their current balance and recent transactions.
*   **UI Component**: `resources/views/components/layouts/dashboard.blade.php` (sidebar balance) and `app/Livewire/Dashboard/PlayerDashboard.php`.
*   **Connected Files**:
    *   `app/Modules/Wallet/Models/Wallet.php`: Stores the `cached_balance` for quick retrieval.
    *   `app/Modules/Wallet/Models/LedgerEntry.php`: Provides the detailed transaction log.
    *   `app/Http/Controllers/Api/V1/WalletApiController.php`: Exposes `/api/v1/wallet/balance`, `/api/v1/wallet/transactions`, and `/api/v1/wallet/withdraw`.

## 🧪 Isolated Test Cases
### 1. Ledger Integrity
*   **Balance Recalculation**: `test_wallet_balance_matches_ledger_sum`
    *   Perform multiple credits/debits (deposit, prize, entry fee, withdrawal).
    *   Assert `cached_balance` equals `SUM(ledger_entries.amount)`.
*   **Immutability**: `test_ledger_is_immutable`
    *   Expect `LogicException` when calling `$entry->update(['amount' => ...])`.

### 2. Deposit
*   **Happy path**: `test_process_deposit_action` — creates `Deposit` record, credits wallet, dispatches `WalletCredited`.
*   **Idempotency**: `test_process_deposit_is_idempotent` — duplicate `provider_reference` returns same deposit, no double credit, `WalletCredited` fired only once.
*   **Stripe Checkout Redirect**: `WalletDashboardTest::test_deposit_redirects_to_stripe_checkout` — wallet UI creates a checkout session and does not credit balance synchronously.
*   **Stripe Webhook Credit**: `StripeWebhookTest::test_checkout_session_completed_credits_wallet` — signed `checkout.session.completed` event credits the wallet through `ProcessDepositAction`.
*   **Stripe Webhook Idempotency**: `StripeWebhookTest::test_checkout_session_webhook_is_idempotent` — duplicate checkout event does not double-credit.
*   **Stripe Signature Guard**: `StripeWebhookTest::test_invalid_signature_is_rejected` — invalid webhook signatures return `400`.

### 3. Withdrawal Security
*   **KYC Guard**: `test_request_withdrawal_blocked_if_kyc_not_approved`
*   **Insufficient Balance**: `test_request_withdrawal_blocked_if_insufficient_balance`
*   **Pending Created**: `test_request_withdrawal_creates_pending_withdrawal`
*   **Four-Eyes (Approve)**: `test_withdrawal_approval_and_rejection_enforce_four_eyes` — requestor cannot approve own withdrawal, asserts `LogicException`.
*   **Four-Eyes (Review)**: `test_withdrawal_actions_require_correct_roles` — unauthorized user throws `AuthorizationException`.

### 4. Withdrawal Lifecycle
*   **Review**: `test_review_withdrawal_action_transitions_status` — status → `UNDER_REVIEW`, `reviewed_by` set.
*   **Reject**: `test_reject_withdrawal_action` — status → `REJECTED`, reason recorded, wallet balance unchanged.
*   **Approve → Debit (via listener)**: `test_create_ledger_entry_listener_debits_wallet_on_withdrawal_approved` — `CreateLedgerEntryListener` debits wallet when `WithdrawalApproved` fires.
*   **Listener Idempotency**: `test_create_ledger_entry_listener_is_idempotent_on_withdrawal_approved` — double-fired event produces only one debit ledger entry.
*   **Process**: `test_process_withdrawal_sets_processed_status_and_timestamp` — status → `PROCESSED`, `processed_at` stamped, **balance untouched** (debit already done at APPROVED stage).
*   **Process Role Guard**: `test_process_withdrawal_requires_role` — unauthorized caller throws `AuthorizationException`.

## 🏗 Architecture Notes
### Withdrawal Debit Flow
The wallet debit does **not** happen inside `ProcessWithdrawalAction`. It happens asynchronously when `WithdrawalApproved` is dispatched:

```
ApproveWithdrawalAction
  └─ dispatches WithdrawalApproved
       └─ CreateLedgerEntryListener (queued, 'wallet' queue)
            └─ WalletService::debit()   ← actual balance reduction
            └─ dispatches WalletDebited
```

`ProcessWithdrawalAction` = "confirm payout was sent externally" step only. It transitions `APPROVED → PROCESSED` and stamps `processed_at`.

### Idempotency Guards
*   `ProcessDepositAction`: checks `provider + provider_reference` uniqueness before crediting.
*   `CreateLedgerEntryListener`: checks for existing `LedgerEntry` with same `reference_type + reference_id` before debiting on `WithdrawalApproved`.

## 🛠️ Feature Gaps & Unused Schema
*   **Missing Features**:
    *   **External Payout Integration**: The `PROCESSED` state is manual; integration with PayPal/Stripe Connect for automated payouts is missing.
    *   **Currency Conversion**: Schema assumes a single currency (e.g., USD); no `currency_code` or conversion logic in `WalletService`.
*   **Unused Schema Columns**:
    *   `deposits.fee_amount`: Field exists (and is now in `$fillable` + `casts`), but currently 100% of deposit is credited — fee deduction logic not yet implemented.
    *   `wallets.metadata`: Placeholder for limits or tags (e.g., "High Roller").
