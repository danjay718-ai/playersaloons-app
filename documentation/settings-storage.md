# Settings and payment credential storage

Global scalar options remain in `system_settings`. Payment credentials are stored separately:

- `payment_gateways` identifies each supported provider.
- `gateway_configurations` records immutable credential versions and their last editor.
- `gateway_credentials` stores encrypted credential values, with one unique name per configuration and an explicit database/environment source.
- `payment_checkouts.gateway_configuration_id` and `withdrawals.gateway_configuration_id` retain the configuration used for that payment. Changing the active gateway does not change pending payments' credentials.

The migration copies existing encrypted overrides without decrypting/re-encrypting their stored values, retains their editor metadata, and removes the old credential rows from `system_settings`. Null overrides retain environment fallback behavior. It also removes the unused `feature.kyc_required` setting; KYC remains mandatory for withdrawals.

## Editing credentials

The admin supports Payop and Stripe credentials, with one active deposit provider at a time. Both providers' saved configurations are retained when switching. Other gateways require a backend checkout/webhook integration and validation before they can be configured here; adding a database row alone does not add provider support.

Use the admin UI when authorized operations staff need to rotate credentials without a deployment. Environment configuration remains available for deployment-managed secrets. Database overrides use Laravel's encrypted cast; keep `APP_KEY` outside the database and repository, and retain the keys needed to decrypt historical configurations. Encryption at rest does not protect credentials from a compromised application server or an administrator authorized to replace them.

A blank field preserves its current value. The explicit reset checkbox restores the environment source when the form is saved. The page displays the source, but never sends saved secret values to the browser. An environment change is captured as a new immutable version before the next payment uses it.

Payop availability checks validate the proposed token and certificate before the settings write transaction begins. A concurrent credential/currency change causes the save to fail and request a reload. Successful credential changes are audited by provider and changed field names, without secret values.

Stripe checkouts are saved locally before contacting Stripe. Their metadata supports recovery if invoice creation succeeds remotely but the local request times out. Webhooks can verify a pending checkout with its original signing configuration, and successful processing marks that checkout completed atomically with the wallet credit. Pre-migration Stripe sessions have no local checkout, so their migration-time verifier is retained for reconciliation. Keep the old merchant account operational until these payments settle.

Payop withdrawal requests retain their configuration for submission, callbacks, and scheduled reconciliation. A provider's externally revoked token cannot be made valid by retaining its encrypted copy; settle outstanding payments before revoking access at the provider.

## Financial rules

- Currency changes are rejected after balances, ledger entries, payments, withdrawals or funded tournaments exist. The first payment/ledger write also freezes the denomination under the same currency-row lock used by the admin change, preventing an initial payment from racing a currency edit.
- Withdrawal minimums and daily totals are enforced in `RequestWithdrawalAction`, shared by Livewire and the API. The default minimum is 10.00 and the daily maximum is 1000.00. Days use UTC. Pending, under-review, approved and processed requests count; rejected and failed requests do not. The wallet row is locked before reading the daily total.
- Grouped settings saves use one transaction, including their before/after activity record. Readers use a scoped settings snapshot; model saves invalidate it. Workers receive a new scope for each job.

## Deployment and rollback

Run `php artisan migrate --force` during deployment before starting the updated application/queue workers. Back up encrypted settings and retain the application encryption key. The migration's rollback restores current database overrides to the previous store, but refuses to discard an earlier credential version still referenced by an unsettled payment.

The local MySQL migration was applied after creating a private encrypted backup under `/tmp/playersaloons-encrypted-settings-before-*.json`. Credential preservation was checked without printing secret values. Generated backups are not part of the repository.

## Verification (2026-11-12)

- Full isolated SQLite PHP suite: 938/938 tests passed, 12,445 assertions.
- Focused admin settings/currency/seeder and wallet tests: 142/142 passed, 849 assertions. Coverage includes both providers' encrypted storage, secret redaction, provider switching, permission enforcement, null-source handling, and rollback protection.
- Focused PHPStan: passed with zero errors. Pint, diff checks and the production frontend build passed.
- Measured admin component `system_settings` reads fell from 19 to 3; the new gateway configurations are read separately and reused within the scope.
- Desktop/phone credential controls were reviewed at 1440 and 390 pixels using an isolated preview database.
- A separate MySQL test database could not be created with the application user's permissions. Parallel MySQL load tests and live provider transactions were not run. The successful local MySQL migration verifies schema application and preservation, not parallel request behavior.
