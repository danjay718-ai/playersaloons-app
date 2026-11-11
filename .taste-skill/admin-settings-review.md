GATE: OPEN 15 (rounds used up)

# Admin settings redesign verification

Follow-up, 2026-11-12: the three PHP failures recorded below are resolved. The full PHP suite now passes **926 / 926 tests**, with **12,354 assertions**, using SQLite in memory. Pint and diff checks pass. The earlier design-gate result remains separate from this test repair.

- Added a forward migration that clears the premature navigation seed marker only for an untouched, uninitialized three-link default menu. Fresh seeding now creates all six configured default links, including Tournaments. Existing accounts and renamed, disabled or deleted links are protected by regression tests.
- Updated the review assertion to check the numeric rating used by the current landing UI, and covered both v3 and v4.
- Updated public H2H navigation assertions to check the correct v3/v4 headers while retaining the check that authenticated dashboard navigation uses its own sidebar.
- The migration was exercised only in the isolated tests; it was not applied to the running application's database. The earlier failures and counts below are the original review record.

Updated `/admin/system-settings` with grouped nested navigation, one active settings panel, direct links and browser history. Switching panels retains unsaved inputs. Payment controls show the credentials for the selected deposit/payout providers. Existing Livewire save actions and authorization remain in place.

## Automated verification

- Full PHP suite: **917 / 920 passed**, 12,232 assertions. SQLite in memory; no application database reset.
- Final focused settings and Payop run: **60 / 60 passed**, 346 assertions. Includes all settings save actions, invalid values, view-only authorization and existing deposit/payout regression cases.
- Admin entry page smoke test: **36 routes passed** (72 assertions), including the CMS subsections. This checks page responses, not every possible click on every page.
- JavaScript: **14 test files passed**, including section selection, invalid/deep-link handling and back/forward restoration.
- Production Vite build, scoped Pint and `git diff --check` passed. The build emits an existing warning for `/images/guest-highlights.webp`.
- A scoped PHPStan attempt exited without diagnostic output; no static-analysis pass is claimed.

## Failures outside the changed settings flow

All three full-suite failures were reproduced individually. They were not fixed in this UI change:

1. `FreshInstallationSeederTest::test_default_seeder_creates_only_approved_core_data`: expects four `public_navigation_items`, receives three. Review the navigation data migrations and fresh-install expectation.
2. `PlayerReviewManagementTest::test_admin_can_approve_review_and_landing_displays_stars`: approval works, but the public landing response lacks the expected five-star text. Review the current landing review presentation and assertion.
3. `TournamentV2WorkflowTest::test_public_head_to_head_navigation_keeps_a_signed_in_player_in_the_public_shell`: the public response lacks the expected `id="public-nav"`. Review the current public shell contract and assertion.

## Visual review

- Kind: software, an internal administrative application.
- Direction: existing Inter typography and account palettes; restrained blue actions, neutral surfaces, consistent controls. The existing light/dark themes are retained. Nested navigation replaces the previous long column of unrelated settings.
- Reviewed viewport screenshots at 1440, 1024, 820, 768 and 390 pixels, plus the dark theme at 1440. The compact layout uses a grouped section selector. Provider cards, inputs and header fit the reviewed screens.
- Screenshot artifacts: `/tmp/admin-settings-final-1440.png`, `/tmp/admin-settings-final-1024.png`, `/tmp/admin-settings-final-820.png`, `/tmp/admin-settings-final-768.png`, `/tmp/admin-settings-final-390.png`, and `/tmp/admin-settings-dark-1440.png`.
- Voice: short operational labels, explicit financial effects, and save actions. No assumed business facts, new name, stand-in contacts, photographs, art or client asset slots were introduced. Webhook domains come from the existing application URL.
- Motion: brief hover/press feedback and reduced-motion support. Marketing hero deals, shaders, scroll moments and promotional photography were intentionally excluded from the working admin form.
- The full-page screenshot tiler timed out on the nested application scroller. The viewport screenshots completed using the skill’s `--no-tiles` option.
- The gate’s standalone layout check and performance script could not finish: `Runtime.evaluate: Inspected target navigated or closed`. No layout-check or performance pass is claimed. The application registers a service worker that can reload the page on controller changes; the viewport captures completed using an initialized profile.

## Gate waivers

- `overlap-text` — The sidebar navigation clips its scrollable links above the fixed footer; offscreen link rectangles are not visible text overlap.
- `focus-ring` — This utility is present in the shared Tailwind bundle for other screens; the settings controls have explicit focus-visible outlines.
- `focus-ring` — This shared bundle utility does not remove the explicit focus-visible outlines provided by the settings controls.

## Open gate findings

These are reported as open, not as a passing design gate. The checker treats this internal application as a marketing page, including its sidebar offsets, long form and utility controls.

1. `no-visual` — no picture, effect or mock in this section; a feature section needs a visual
2. `copy-actions` — 3 actions in the hero ("Copy URL", "Copy URL", "Save payment settings"); at most 2
3. `fold-action` — the hero's main button "Copy URL" starts 671px below the first screen (900px tall): it is not seen on load
4. `moment-none` — a software page (--type) deals no moment: every kind carries one, two on a long page (moments.md)
5. `hero-screen` — the hero ends at 1835px on a 900px screen, 935px below the fold: it ends at the fold, 1px either way (SR-1)
6. `section-height` — the section starts at 96px, 96px below the screen grid (0 x 900px): sections start on whole screens (SR-2)
7. `one-section` — a <div> sits directly in <main>: every part of <main> is a <section> (SR-4)
8. `one-heading` — the hero holds an h2 (1): its heading is the h1, and an h2 opens the next section (SR-5)
9. `container-edges` — the nav's content runs 306px from the left and 32px from the right (read from its outermost blocks: no .wrap): the page's container starts at 288px; 306 and 32px are not one margin; the margin is 64-120px at 1440px (one container, equal margins, 2px either way) (SR-6)
10. `container-edges` — the content of section 1 runs 288px from the left and 32px from the right (read from its outermost blocks: no .wrap): 288 and 32px are not one margin; the margin is 64-120px at 1440px (one container, equal margins, 2px either way) (SR-6)
11. `container-edges` — a <a> at the top of the page, outside header.nav, main, footer.closing and nav.actionbar (SR-6)
12. `container-edges` — a <div> at the top of the page, outside header.nav, main, footer.closing and nav.actionbar (SR-6)
13. `container-edges` — 2 text blocks start on a left line at x 537, 249px from the container's left: text starts on the container's left, or on one split line (x 765, 860, 955) (also "Credentials are encrypted. Leave") (SR-6)
14. `container-edges` — the h2 starts 222px right of the container's left: every h1 and h2 starts on it, 2px either way (and 2 more like it: x 380, x 300) (SR-6)
15. `container-edges` — a box over half the container wide runs 510-1408px, not on the container's lines at 288-1408px (SR-6)

## Warnings retained

- `off-grid` — left edge at 300px, 12px off a common edge of its section at 288px (3 elements, like p:nth-of-type(2)); 2 more in this section off the grid too (p at 300px, p at 300px) (inside a held section or scroll scene, whose motion --quick does not check)
- `copy-length` — 1 section over 90 words a screen outside the UI mocks ("System settings" 110); 110 words a screen across the page (209 words in 1.9 screens): a tech page says it in about 80 or fewer
- `fold-action` — the hero's second button "Copy URL" starts 759px below the first screen (900px tall): it is not seen on load
- `fold-action` — the hero's second button "Save payment settings" starts 850px below the first screen (900px tall): it is not seen on load
- `page-meta` — no <meta name="description">: a search result and a shared link show a scrap of the page instead
- `share-meta` — the head lacks og:title, og:description, og:image, twitter:card: a shared link shows no picture or title of its own
- `form-fields` — the form has no status line (role="status"): sending, sent and errors are never read out
- `copy-rhythm` — every sentence about 8 words long (spread 4.0)

## Limits

No live Payop transaction, external payout or merchant credential validation was performed. Browser screenshots verify rendering; they do not establish that every admin interaction works. The feature suite checks the existing workflows in an isolated test environment. Real deployment webhook reachability remains outside this UI task.
