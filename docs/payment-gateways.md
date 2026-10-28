# Payment gateways

Deposits support Stripe Checkout and PayOp hosted checkout. Administrators with
`system_settings.manage` can configure them at **Admin → System Settings → Payment gateway**.

## Setup

1. Run `php artisan migrate` to create `payment_checkouts` and add payout tracking to withdrawals.
2. Enter the PayOp project's public key and secret key in System Settings.
3. In the PayOp merchant project, set the **Checkout IPN URL** to
   `https://YOUR-DOMAIN/payop/webhook`. The exact URL appears in System Settings.
   The application must be publicly reachable over HTTPS.
4. Select **PayOp**, enable **Accept new deposits**, and save.
5. Complete a merchant-approved payment and confirm that the invoice, wallet
   credit, processing fee, and ledger entry agree before opening payments to players.

Use the test process supplied by PayOp for the merchant account. There is no
application sandbox toggle: gateway credentials and merchant configuration determine
whether funds are real. Do not assume Stripe test cards work with PayOp.

Stripe remains the default until an administrator changes the setting. Existing
Stripe environment credentials continue to work. To configure Stripe through the UI,
enter its secret key and webhook signing secret, using `/stripe/webhook` for
`checkout.session.completed` events. PayOp environment fallbacks are
`PAYOP_PUBLIC_KEY` and `PAYOP_SECRET_KEY`.

## Behavior

- Settings apply to new checkout requests immediately, without deploying code.
- Blank credential fields preserve existing values. Saved credentials are encrypted
  using Laravel's application key and are never loaded back into form fields.
  Preserve `APP_KEY` with database backups.
- Turning deposits off prevents new checkouts; both webhook endpoints keep processing
  payments already started, regardless of the selected gateway.
- Keep the original merchant account and Stripe webhook signing secret operational
  until its pending payments finish. Switching gateways does not migrate invoices
  between merchant accounts or support multiple historical signing secrets.
- PayOp credits use the saved checkout amount and fee. A server-to-server invoice
  lookup verifies paid status, invoice ID, order ID, amount, and currency before
  wallet credit. Browser redirects and IPN payment claims do not authorize credits.
- A locked checkout row and database transaction prevent repeated notifications from
  crediting the same PayOp deposit twice. IPNs can recover an invoice after a local
  creation timeout by verifying its saved order ID.
- Invoice verification outages return a retryable error. Currency mismatches are
  rejected: do not change the platform currency while payments are pending.
- Withdrawals default to manual finance processing. PayOp bank payouts can be enabled
  independently of the deposit gateway; Stripe deposits can fund withdrawal requests
  routed to PayOp. This does not transfer funds from Stripe into the merchant PayOp
  balance: treasury must fund that balance separately.

## PayOp bank payouts

Payouts are prepared for **PBB bank transfer, method 20**. Other PayOp payout methods
and automatic Stripe payouts are not implemented.

1. Confirm with PayOp that external-recipient bank payouts are enabled for the merchant
   and wallet currency. A checkout project key alone does not enable payouts.
2. Obtain the merchant payout JWT token and download the binary `x25519.pub` certificate
   from PayOp **Settings → Certificates**.
3. In System Settings enter **PayOp payout JWT token**, upload the certificate, select
   **PayOp bank transfer (PBB)** under **Withdrawal processing**, and save. Enabling
   requires a successful authenticated check that method 20 supports the current currency.
4. Set the **Withdraw IPN URL** in PayOp to
   `https://YOUR-DOMAIN/payop/payout-webhook`. This is separate from Checkout IPN.
5. Keep the scheduler running. `payments:reconcile-payop-payouts` checks pending payouts
   every ten minutes if callbacks are missed. Finance can also use **Refresh PayOp status**.

The player supplies the bank account holder name and a checksum-valid IBAN. The
verified account email, amount, currency, recipient details, and payout provider are
captured on the request. Bank details are encrypted in storage and excluded from model
serialization. API withdrawal requests accept `name` and `iban` alongside `amount`.

Finance reviews and approves the request. Approval debits the wallet synchronously
before any external payment, regardless of queue timing. Finance then selects **Send
to PayOp**. The payload is encrypted with Sodium sealed boxes and submitted with the
withdrawal UUID as both `clientId` and `idempotency-key`. Accepted submission appears
in PayOp; it does not mean the recipient has already been paid.

Authenticated verification checks the provider reference, internal withdrawal UUID,
amount, and currency before applying a status. The response must contain the matching
withdrawal in `data`, with `identifier` (or `id`), `status` (or `state`), `amount`,
`currency`, and the submitted `metadata.internalWithdrawalId`. Unexpected responses
remain unresolved. Verify this response contract with the actual merchant account
before enabling live payouts; automated tests use mocked responses.

- Confirmed accepted payouts become **processed** without a second debit.
- Confirmed rejected payouts become **failed**, and the reserved amount is returned
  exactly once through the ledger. A new withdrawal request can then be submitted.
- Explicit submission refusals (HTTP 400/401/403/422) also fail and return the funds.
- Timeouts, ambiguous server failures, malformed success responses, and duplicate-key
  conflicts remain **unknown** with funds reserved. They cannot be resubmitted or
  manually marked paid through the app. An IPN can recover a lost provider reference.
  If necessary, finance can enter the PayOp reference from its merchant dashboard and
  use **Refresh PayOp status**; the API must match the selected withdrawal before binding.
  If no payout exists remotely, contact support for reconciliation; do not create a
  replacement payout or refund while the outcome remains uncertain.
- Conflicting final statuses require manual reconciliation. They do not automatically
  trigger another debit or refund.
- Keep merchant payout credentials operational until all submitted payouts finish.
  Switching the default provider affects new requests only. Do not change the platform
  currency while withdrawals are pending. Refunds into frozen wallets remain pending
  until the wallet can accept the credit.
- Provider payout fees are paid from the merchant balance (commission type 1), so the
  requested amount is sent to the recipient. PayOp controls availability and processing
  time; merchant funding and bank processing can prevent immediate payment.

## Verification

`php artisan test tests/Feature/Wallet tests/Feature/Admin/CurrencyAndIncomeTest.php tests/Unit/StateMachines/WithdrawalStateMachineTest.php`

These checks fake gateway HTTP requests; they do not prove merchant activation or
end-to-end payment availability for a specific PayOp account.

Official references: [hosted checkout](https://github.com/Payop/payop-api-doc/blob/master/0.Integration/integrationApiTypes.md),
[invoice API](https://github.com/Payop/payop-api-doc/blob/master/1.Invoice/invoice.md),
[signature](https://github.com/Payop/payop-api-doc/blob/master/0.Integration/signatureGenerator.md),
[IPN](https://github.com/Payop/payop-api-doc/blob/master/5.IPN/ipn.md),
[withdrawals](https://github.com/Payop/payop-api-doc/blob/master/3.Withdrawal/withdrawal.md),
[payout encryption](https://github.com/Payop/payop-api-doc/blob/master/3.Withdrawal/withdrawalEncrypt.md).
