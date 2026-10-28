# GamersRival — blue arena refinement

Branch: `feat/guest-page-redesign`. Existing work retained; no commit or deployment.

The user's corrections govern this iteration: consistent footer size across every public layout, less saturated purple, more purposeful text colour, readable Join Now hover, a draggable collection of registration-open events with a countdown, and a player-win ticker instead of registration notices.

Direction retained: name-as-monument, seed 88616077; the existing crown, GR marks, Inter/Orbitron and owned footage remain. No new direction draw or external imagery. The palette follows the user's blue preference: navy/ice blue for the arena, cyan for navigation and game identity, gold for time, mint for entry and winnings. The requested web3 gradients and quiet elevation remain intentional exceptions to the skill's flat-only house style. Auth uses the same public palette and footer while preserving its quiet form.

Scope and implementation:
- The guest footer now spans the page with one shared inner container. Homepage, landing/content pages, app-layout guest pages, login and register all render the same partial. Auth's separate small footer was removed; authenticated layouts retain their existing footer.
- Join Now's text keeps its dark ink on hover. The generic nav-link hover selector now excludes the filled CTA.
- Hero cards come from a separate bounded query of up to eight real, future registration-open public tournament occurrences. Ongoing, elapsed, inactive-game and standalone private duels cannot enter it. Native horizontal scrolling and scroll snap provide the fallback; mouse drag, touch swipe, arrows, dots, keyboard arrows and a next-card glimpse make the collection discoverable. There is no automatic slide advance.
- Countdown uses the actual ISO start timestamp and updates once per second. At expiry it says Starting now. Selection/status labels refresh on page load; the timer itself stays live.
- The stream now contains only completed public match wins from the last week, capped at eight and ordered by completion time. Winner usernames and tournament names are public labels; no legal names, balances or deposits appear. A reward is attached only to the champion's final win in a completed tournament with a completed rank-one PrizeDistribution for that winner's wallet. Earlier-round wins and pending prizes never inherit that reward. It uses configured Currency formatting, not a fictional credits unit. Public counts and the win stream retain visible 30-second polling, pause and reduced-motion support.
- New copy is in lang/en.json. No dependency, schema, financial mutation or authentication-flow changes.

ROUND 1
Seen in one second: a blue cinematic arena with an actionable real tournament.
Idea (one sentence): GamersRival's crowned identity becomes an active competition lobby with time and results carrying the accent colours.
Live thing: manually browsable real registrations and ticking starts, followed by verified player results.
Layout words by section: hero=arena-and-carousel / schedule=entry-list / games=cover-gallery / honours=weekly-sheet / steps=sequence / stats=ledger / features=menu / reviews=quote / footer=shared-brand-monument
Scores: design 7.5 · usability 7 · creativity 7 · content 7 → raw overall 7.2
Caps applied: the strict ornament rubric remains capped at 6.5 for the user's requested web3 effects.
Top three problems, screenshot and fix:
1. guest-blue-debug.jpg: countdown units were undefined because Alpine's $el referred to the current time element. Capture the root's localized labels on init; verify the complete countdown pattern and ticking seconds.
2. guest-blue-browser/mobile-slider.jpg: section-heading CSS enlarged tournament titles to 28px, forcing three lines. Give card headings their own 21px rule.
3. guest-blue-reviewed/desktop-fold.jpg: a 9ch title constraint produced four lines during font loading. Remove that constraint; final desktop and mobile renders show two lines.

ROUND 2 — final
Seen in one second: a confident blue arena, a large two-line competitive headline and a real entry panel with a gold countdown.
Idea (one sentence): the GR crown and owned competition footage anchor a lobby where blue guides discovery, gold signals the start, and mint signals the win.
Live thing: draggable registrations with live time remaining, real availability and confirmed player wins.
Layout words by section: hero=arena-and-carousel / schedule=entry-list / games=cover-gallery / honours=weekly-sheet / steps=sequence / stats=ledger / features=menu / reviews=quote / footer=shared-brand-monument / account=story-and-form
Scores: design 8 · usability 8 · creativity 7 · content 7 → raw overall 7.7
Caps applied: literal house-style score capped at 6.5 by the deliberately retained web3 gradients/outlines/elevation; creativity capped at 7 because the cinematic gaming approach is familiar. Raw score describes the requested aesthetic, not flat-house-style compliance.
Remaining limitations:
1. complete-home/mobile-page-01.jpg: the hero's full tournament details extend beyond the first phone screen. The primary Explore action remains visible immediately; readable details are retained.
2. complete-home/desktop-page-01.jpg: game covers form a repeated collection. Retained for game selection; neighboring sections have different compositions. One rendered inspector warning.
3. complete-home/desktop-fold.jpg: responsive display type and tournament metadata produce more computed font sizes than the skill prefers. Running paragraphs remain 16px. The other homepage warning.

Verification:
- `php artisan test tests/Feature/CMS/GuestCompetitionTest.php tests/Feature/CMS/LandingPageTest.php`: 20 passed, 128 assertions, after final read-model changes.
- Targeted Pint, scoped PHPStan for the competition service and both landing components, production build, and git diff whitespace check pass.
- Browser checks: live countdown format/ticking, next/previous controls, dots, real mouse drag, keyboard arrows, native phone swipe, readable hover text, reduced motion, zero mobile overflow and no console exceptions.
- Footer measurement at 1440px on /, /about, /contact, /tournaments, /policies, /login and /register: all width 1440, height 538.8125, heading 42px and brand type 115.2px. Server regression also compares identical footer markup on all seven routes.
- Final original skill inspection at 1440/768/390: `/tmp/guest-blue-complete-home` = 0 FAIL / 2 WARN; login and register in corresponding directories = 0 FAIL / 1 WARN each (colour variety from existing brand imagery). Opened every fold and full-page sheet for all three pages after the final visual change.
- The browser harness suppresses only the existing service-worker first-install reload in its test browser and waits for document load before measurement; production service worker behavior is unchanged. Early interrupted inspection attempts are not counted as passes.
- Compared with `/tmp/web3-reviewed-home` and `/tmp/web3-final-login`: less purple, more semantic colour, consistent public footer and a discoverable multi-event hero.
- Current local data has no recent eligible match wins, so the visible ticker has an honest empty state. Real fixture-backed tests prove winner/prize display, pending-prize exclusion, final-only reward attribution, cap/privacy and deleted-user exclusion. No sample wins were inserted into local app data.
- Full repository suite and physical-device PWA installation were not repeated. Prior unrelated admin seeder/test changes remain untouched; generated service-worker/version changes from builds were restored.
