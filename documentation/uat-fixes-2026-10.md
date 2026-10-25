# October UAT fixes

| UAT item | Result |
| --- | --- |
| 1. Timezone resets and inconsistent displays | The workflow migration preserves existing `tournament.timezone` settings. Existing seeders also preserve the saved setting. Stream tournament cards and the admin competition finance list now use the same timezone service as tournament pages, lists, filters, and sidebar clocks. Database timestamps and deadlines remain UTC instants. |
| 2. Topbar balance | The existing balance component is visible at every screen width. It refreshes every ten seconds while visible and responds to the existing wallet update event. |
| 3. Third false-result strike | Admin dispute notifications and the review dialog warn when a participant already has two confirmed false-result strikes. Opening a dispute alone does not issue a strike. |
| 4. Loss report followed by opponent timeout | A single loss or draw followed by a timeout opens an auditable dispute without assigning a winner or changing the report. Both players can upload evidence. The existing win/loss confirmation, draw/draw rematch, and conflicting-pair dispute behavior is retained. |
| 5. Different participant and spectator screens | Personal defeat and tournament-wide ongoing status represent different facts. Nonparticipants now receive an explicit spectator explanation. Existing registration and match permissions remain enforced. |
| 6. Repeated BYEs | Both tournament workflows choose the advancing player with the fewest previous BYEs. A player with a previous BYE cannot receive another while an eligible advancing player has none. Rounds needing a BYE wait for every source result before allocating the next round. Fixed pairings remain in the older workflow's even rounds. |
| 7 and 9. XP and level | Profiles show saved progression instead of a hard-coded level. Opponent profile responses, chat messages, and match player cards expose level and XP. Private account fields are not added to public responses. |
| 8. Finishing before a ban and banned login | Existing third-strike bans remain deferred until elimination or tournament completion; manual admin bans remain immediate. A valid login for a banned account now receives a specific banned-account response. Incorrect passwords retain the generic credentials response. |
| 10. Below-capacity notice | The requested title and body use the actual joined count and capacity. |
| 11. Translation discovery and duplicates | English sync includes admin, player, and guest sources. Date-format arguments are excluded. Identical English phrases are listed/exported once; saves and imports update equivalent legacy keys. Recurring tournament titles contribute the shared name without the generated date/time suffix. Historical dated keys remain stored but are excluded from the work list and export. |
| 12. Filipino import rejection | Adding a language or adding new English phrases no longer invalidates an unchanged export. The importer validates the exported snapshot and all existing sources, rejecting malformed, missing, duplicate, changed, or conflicting entries and broken placeholders atomically. New phrases remain untranslated. |

## Deployment and UAT

Run normal migrations and restart long-running workers with the deployment. No new migration or schedule conversion is introduced by these fixes. If an earlier deployment already replaced the intended timezone with UTC, select the intended timezone once in Settings; the previous preference cannot be inferred safely. Future migrations and seeding preserve it.

In Translation Manager, run **Sync English** and **Sync Database**, then export English to translate newly discovered admin and competition copy. Existing language definitions and translated values are retained. Previously exported files remain valid when their existing source phrases are unchanged; newly added phrases are completed in a later export. Actual translated wording still comes from the administrator's translation file.

Confirm the sidebar and tournament times in summer and winter dates, refresh the balance after a wallet transaction, review a dispute involving a player with two strikes, and test a loss report followed by an opponent timeout. Check an underfilled five-player tournament through successive rounds to see distinct BYE recipients.

The broad regression run also corrected an API invitation test fixture to assign the required PLAYER role to its invitee. Invitation eligibility rules are unchanged.

## Verification

```sh
./vendor/bin/phpunit
./vendor/bin/pint --dirty --test
npm run build
./vendor/bin/phpstan analyse --no-progress --memory-limit=1G
```

Browser checks use isolated test data at 1440, 768, and 390 pixels. The narrow changes preserve the existing site theme; the design inspector continues to flag existing theme choices outside this UAT scope. Static analysis is compared against the committed source so existing repository findings are reported separately from new findings.

Latest local verification: **809 tests passed, 11,503 assertions**, and all nine focused browser checks passed across the three widths. The production asset build and changed-file Pint check passed. Full-repository Larastan reports 377 existing findings against 382 on the committed baseline, with no new findings. Full-repository Pint also reports existing formatting issues in unchanged files. Production deployment and live UAT have not been performed.
