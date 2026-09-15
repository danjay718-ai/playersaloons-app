# Proposed fresh installation / test-data reset

Status: prepared for review. No existing database has been reset.

## Objective

Start with the production bootstrap configuration and no testing activity. Preserve a verified backup of the old database and files before switching. This is a one-time test-data retirement proposal; it is not a production feature for deleting customer history.

## Seeded baseline

| Resource | Initial state |
| --- | --- |
| Roles and permissions | Nine system roles, current permission catalog, assignments by role. PLAYER and TEAM_CAPTAIN roles exist without player accounts. SUPER_ADMIN receives all permissions, including game soft deletion, restore and permanent deletion. |
| Staff directory | One SUPER_ADMIN and one ADMIN, configured through deployment secrets. Staff profiles and zero-balance wallets are created. |
| Internal platform account | Required platform processing account and zero-balance wallet. Its password is random; it is not a shared staff login. |
| Landing page | Eight sections, explanatory content, stat definitions and footer links. No sample testimonials, players, games or activity counters. |
| Public navigation | Tournaments, Teams, Updates and Dashboard, with their default visibility. |
| Policies | Terms and Conditions, Cookie Policy, Privacy Policy, Refund and Cancellation Policy, Disclaimer, and footer links. Content comes from the versioned policy seeder. |
| System settings | Defaults for commission, tournament timezone/result timing, wallet limits, referrals, deposit fees, localization visibility and login limits. |

All other records start empty: platforms, games (including Deleted Games), game translations/defaults/assignments, translation management rows, players, teams, tournament templates/slots/occurrences/registrations/brackets/matches/disputes, standalone H2H challenges/matches/ratings, ledger and wallet transactions, deposits/withdrawals/refunds/prizes, KYC/compliance history, progression/referrals, streams/chats/follows, announcements/notifications, advertisements, reviews, inquiries, newsletters, blog/news content, country blocking, sessions/tokens/jobs and audit/error/monitoring history. Deployment and normal requests may create fresh sessions, jobs or logs after bootstrap.

Source-controlled language JSON files and public assets remain available. Opening Translation Management leaves its database empty; Sync JSON is an explicit administrative action.

This baseline recreates the two default human staff accounts. Existing additional staff, custom roles/assignments, edited CMS/landing/policy content, settings and balances are not carried over automatically. Inventory any approved exceptions before reset approval and recreate/import them separately. Do not assume a seed operation preserves those records after a database reset.

## Production credentials

Configure these through the deployment secret manager before bootstrap:

- `BOOTSTRAP_SUPER_ADMIN_EMAIL`, `BOOTSTRAP_SUPER_ADMIN_USERNAME`, `BOOTSTRAP_SUPER_ADMIN_PASSWORD`
- `BOOTSTRAP_ADMIN_EMAIL`, `BOOTSTRAP_ADMIN_USERNAME`, `BOOTSTRAP_ADMIN_PASSWORD`

Outside `local`/`testing`, both human accounts require unique valid emails/usernames and supplied passwords of at least 12 characters with lower/upper-case letters, a number and a symbol. Known demo passwords are rejected. Bootstrap validates this before inserting core seed data. Local/testing retains the existing development login defaults.

Reseeding staff does not reset existing passwords, profiles or wallet balances. Existing deleted bootstrap accounts require explicit restoration. Settings seeding inserts missing defaults without replacing edited settings. Landing/navigation/policy seeders apply their versioned defaults; do not rerun the full baseline on an established production database as a routine deployment step.

## Proposed execution after approval

Prefer a new empty database over dropping the currently configured database. Retain the old database as the rollback source. Standard deployment credentials should not have permission to drop unrelated databases.

1. Agree on the environment, exact database name, retained exceptions, deployment version and maintenance window. The environment must contain test data only; no production customer history is included in this proposal.
2. Put the application into maintenance mode. Stop queue workers, Horizon if used, scheduler, Reverb and any other writers. Pause payment/provider webhooks and pending external operations for this environment. Capture pending jobs before discarding obsolete testing jobs.
3. Back up the full database and uploaded files, plus the current deployment configuration and secret references. Verify the backup can be restored into an isolated database. Record the old release and the backup location.
4. Provision the new empty database. Change `DB_DATABASE` and its connection secrets to the approved new target. Verify the resolved database connection before running migrations. Use a new application-specific Redis/cache/session/queue namespace, or clear only this application's old test state while all writers remain stopped. Never flush shared Redis blindly. Keep backups/uploads available; this reset does not delete uploaded files.
5. Clear cached configuration after the connection/secret change, then initialize the new database:

   ```sh
   php artisan config:clear
   php artisan migrate --force
   php artisan db:seed --class=FreshInstallationSeeder --force
   php artisan permission:cache-reset
   php artisan config:cache
   php artisan migrate:status
   ```

   `DatabaseSeeder` delegates to the same core baseline. Do not use `DemoSeeder` or individual demo fixture seeders; these reject production environments. Do not use the System Settings tournament-only reset as a full-system reset.

6. While writers remain stopped, verify the seeded matrix: nine roles; three users (two staff and one internal account); three zero-balance wallets; no players, platforms, games, translations, tournaments or financial records. Verify all migrations ran and Super Admin has the latest deletion/recovery permissions. Check zero player/game/activity counters.
7. Check staff login, role/permission pages, landing/navigation/policy links and empty management screens. Opening Translation Management must not import rows. Confirm demo credentials are rejected by the production seed path, and the tournament testing-reset control is unavailable in production.
8. Enable only approved integrations and restart the services against the new database and queue namespace. Keep maintenance mode until the checks pass. Record approval, execution time, release and results outside the database being replaced.

## Rollback

If bootstrap or validation fails, keep maintenance mode and writers stopped. Restore the previous release/configuration and reconnect to the preserved old database/cache/queue namespace. Rebuild configuration caches, verify the old state, then restart its services. Do not replay discarded test jobs into the new database. If activity has already been accepted on the new database, reconcile it before rollback; switching databases alone does not undo external payments or emails.

## Approval scope

Approval is for the named environment/database, the matrix above, any explicitly listed retained exceptions and the verified backup/rollback procedure. Implementation preparation does not authorize executing the reset. Uploaded-file deletion and deployment to another environment require a separate explicit scope.
