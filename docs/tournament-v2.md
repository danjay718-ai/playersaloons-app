# Tournament V2 rollout and architecture

Tournament V2 is an additive workflow selected by `workflow_version = 2` and guarded by `TOURNAMENT_V2_ENABLED`. Existing records remain on V1. Enable the flag only after the migration, scheduler, Redis queue workers, mail transport, and a staging smoke test are complete.

## Boundaries

- `TournamentTemplate` is the reusable definition; `TournamentScheduleSlot` stores unique daily, weekly, or monthly local timetable rows.
- `Tournament` is an occurrence snapshot. The unique `(schedule_slot_id, occurrence_period_key)` key makes rollover retry-safe and preserves historical participants, matches, money, and XP.
- `V2TournamentLifecycle` owns V2 state transitions. V1 remains in `TournamentLifecycleReconciler` and is selected per record.
- `V2PrizePolicy` calculates in integer minor units. Financial values are finalized before bracket play and every debit, refund, commission, penalty, and payout has an idempotency key.
- `MatchAttempt` preserves every original result/rematch attempt. Result submission and scheduled timeout resolution lock the same rows.
- Browser-only interaction such as modal visibility, schedule-row editing, and countdown display uses Alpine. Livewire/controller requests are retained only for authoritative state changes.

## V2 lifecycle

An occurrence is immediately visible as `REGISTRATION_OPEN` and accepts entries only until `start_at`. At `start_at`, two or more entries start and generate a BYE-capable bracket. Zero entries cancel immediately and are hidden; one entry cancels and is refunded. `end_at` remains the occurrence boundary and does not extend registration.

The removed five-hour closing warning is intentionally absent. `end_at` is the occurrence boundary; `join_closes_at` is aligned to `start_at` and is not a warning threshold.

## Payout policy

Payouts use actual confirmed paid entries, never an advertised fixed pool. A full 2- or 4-team occurrence uses 10% platform commission and awards the remaining 90% to First Place. A full occurrence with 6 or more teams uses 10% platform commission, 75% First Place, and 15% Second Place. Any underfilled occurrence that starts with at least two teams uses 15% platform commission and awards 85% to First Place only; the bracket visibly applies BYEs where required. Free-entry occurrences always have a $0.00 payout.

This policy is snapshotted and finalized before bracket progression. Any future change must be introduced as a new policy version rather than recalculating historical occurrences.

## Round deadlines and unresolved finals

`round_duration_seconds` is optional. When configured, the first match started in a round establishes the shared round deadline. A player who has already submitted a result keeps the existing five-minute response window even when the round deadline arrives. An unresolved non-final with no submissions becomes a double no-show: neither side receives XP or a refund, and the legitimate opposing branch advances.

When the last unresolved match is a final, the 30-minute stalled timer does not create a rematch or an automatic champion. The final remains ongoing and accepts normal result submissions. Its payout is held until 24 hours after that occurrence's `end_at`; then an admin is notified through the dispute workflow. The admin can award either finalist or select **No Champion**. No Champion takes exactly 10% of finalized gross entry fees as commission and divides the remaining 90% between the two finalists. An odd minor-unit remainder goes to Player A's bracket slot. The settlement is idempotent.

## Legacy fields

The following V1 fields remain in the schema for historical records and rollback safety, but V2 does not configure them: tournament-level timezone, registration duration, extra registration time, get-ready time, extra-wait time, configurable Play XP, draft/publish choice, and automatic underfill cancellation. Do not drop them until V1 records have completed and a separate archival migration has been approved.

The V1 Livewire tournament form is retained for editing historical V1 records only. When the V2 feature flag is enabled, new-tournament navigation uses the additive V2 Blade/Alpine schedule creator; the legacy creation route redirects there. This preserves the V1 workflow without allowing new data to re-enter it.

## Empty occurrence retention

V2 occurrences exist only for the current valid recurrence window, so future-day records are not pre-created. A zero-registration occurrence cancels at its start and is permanently removed at `end_at`; it is never displayed as ongoing. The cleanup refuses to delete an occurrence with any participant, match, financial, cancellation-vote, team, stream, rule, or announcement data. Occurrences with player or monetary history remain immutable.

## Operations

Run the Laravel scheduler every minute and keep queue workers active for the default/wallet/mail workloads. Before enabling V2, run:

```bash
php artisan migrate --pretend
php artisan test tests/Feature/Tournament tests/Unit/Tournament
php artisan schedule:list
```

Rollback is the feature flag first. Do not roll back the migration after live V2 activity without exporting V2 occurrence, wallet, dispute, attempt, cancellation, strike, and XP records.
