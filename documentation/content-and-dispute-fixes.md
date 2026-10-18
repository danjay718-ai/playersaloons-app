# Landing content and dispute follow-up

The web container runs `LandingPageSeeder` during startup. This seeder now creates
missing defaults only. Existing section/item copy, media paths, ordering, visibility,
custom footer links, and soft deletions remain under admin control on redeploy.
Previously overwritten copy must be entered again or recovered from a backup.

The player tournament view uses “First Match”, omits the estimated-payout note
below About This Tournament, and hides Participation XP in Quick Info. XP awards
and prize calculation rules are unchanged.

Apply migration `2026_10_18_000002_add_pending_dispute_bans` before running the new
application and workers. A third or subsequent dispute strike records a pending
automatic ban. It is applied after the player loses a resolved match in that
strike's competition, or that competition completes, cancels, or refunds.
Rematches and wins in intermediate rounds allow the player to continue.
Financial strike penalties still apply when issued. Manual admin bans remain immediate.

Match/tournament listeners apply eligible pending bans after commit; the existing
minute scheduler also dispatches a recovery job. Applied pending records are
consumed, so restoring an account does not replay the same automatic ban.
Existing bans are not lifted or rescheduled by the migration.

For V2 tournaments and platform Head-to-Head, the first result in each attempt
persists an opponent notification after commit without a notification queue worker.
The message includes that attempt's configured response window, exact deadline in
the app timezone, and a link to Submit Result. Notification preferences still apply;
email and realtime broadcast delivery use their existing queues. Resolved, expired,
and old rematch attempts do not produce new response reminders.
