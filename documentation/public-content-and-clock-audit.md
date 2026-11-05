# Public navigation, content and competition clocks

Reviewed the active v4 landing page, public navigation/footer, tournament and platform H2H discovery, competition details and notification links.

| Content | Admin editor | Result |
| --- | --- | --- |
| Public menu labels, destinations, visibility and order, including Home and Head-to-Head | `/admin/cms/navigation` | One menu serves guests and authenticated public pages. No separate authenticated public header. |
| Hero title, subtitle, body, video and CTA | `/admin/cms/landing`, Hero | Existing CMS fields; the SEO description now also reads the hero body. |
| DOMINATE / GET PAID / RANK UP | `/admin/cms/landing`, Hero, **Hero rotating phrases** | One phrase per line. The final phrase in Title remains the initial rotating phrase. Clearing the extra phrases keeps only that initial phrase. |
| Footer competition, game, how-to, about and contact links | `/admin/cms/footer`, footer items | Removed fixed links, including an H2H link that previously pointed to tournaments. |
| Footer brand text, disclaimer, copyright, social links and company details | `/admin/cms/footer` | Stored in system settings. Company details retain line breaks. |
| Other v4 landing headings, steps, stats labels, FAQ, CTA and empty states | `/admin/cms/landing` | Already stored in landing sections/items. Reviews and leaderboard values come from player records. |
| Standard actions/statuses such as Sign In, Dashboard, Install App, Starts in, Wins and Win rate | `/admin/translations` | Interface translations, rather than marketing content fields. Discovery headings, prompts and filters now also use translations. |

Brand artwork, layout classes, route names and permission checks remain application code. The retained v3 landing is a legacy design behind `LANDING_VERSION=v3`; its slider clock also uses server time.

The navigation seeder previously overwrote edits and restored deleted links on each container startup. It now initializes defaults once, records initialization in system settings, and leaves subsequent admin changes alone. The migration marks existing menus as already initialized.

## Time and timezone

- Display timezone comes from the existing `tournament.timezone` system setting through `TournamentTimezone`. Database timestamps remain UTC; changing display timezone does not reschedule competitions.
- Admin V2 schedules, tournament/H2H lists, detail countdowns and match-result deadlines already shared `serverClock`. Guest v4 countdowns and the retained v3 slider now use that clock as well.
- Desktop and mobile competition cards render the same countdown component. Scheduled times in countdown tooltips use the app timezone.
- Regression coverage includes device-clock skew, delayed component initialization, fresh responses, device sleep, and Amsterdam summer/winter offsets.

## Cancelled and missing competitions

Notification actions check the current competition before navigating. Cancelled, refunded, draft, deleted and unavailable V2 competitions do not expose a details action, including through the notification API. Clicking a stale notification still marks it read and explains why details are unavailable. Old public/player bookmarks to cancelled, refunded or missing competitions redirect to discovery with a message. Staff retain access to existing cancelled records; player entry/refund history is retained.

Cancelled/refunded registrations no longer grant restricted match-room access. Withdrawing from an otherwise active competition still permits its public overview.

## Deployment

Run the new migrations and deploy the rebuilt assets. Content backfills preserve existing, disabled and deleted CMS records. The managed-copy migration retains content on rollback because admins may have edited it.
