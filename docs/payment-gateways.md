# Payment gateways

Deposits support Stripe Checkout and PayOp hosted checkout. Administrators with
`system_settings.manage` can configure them at **Admin → System Settings → Payment gateway**.

## Setup

1. Run `php artisan migrate` to create `payment_checkouts`.
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
- Withdrawals retain the existing finance review and external disbursement workflow.
  Automated payouts, refunds, and new gateway integrations require separate work.

## Verification

`php artisan test tests/Feature/Wallet/PaymentGatewayTest.php tests/Feature/Wallet/StripeWebhookTest.php tests/Feature/Wallet/WalletDashboardTest.php tests/Feature/Admin/CurrencyAndIncomeTest.php`

These checks fake gateway HTTP requests; they do not prove merchant activation or
end-to-end payment availability for a specific PayOp account.

Official references: [hosted checkout](https://github.com/Payop/payop-api-doc/blob/master/0.Integration/integrationApiTypes.md),
[invoice API](https://github.com/Payop/payop-api-doc/blob/master/1.Invoice/invoice.md),
[signature](https://github.com/Payop/payop-api-doc/blob/master/0.Integration/signatureGenerator.md),
[IPN](https://github.com/Payop/payop-api-doc/blob/master/5.IPN/ipn.md).
