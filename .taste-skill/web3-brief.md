# GamersRival — web3 tournament lobby and player access

User correction: two flat arena revisions were not sufficiently distinctive. The requested outcome is a web3 visual feel, still clearly a competitive tournament platform, with matching login/register pages, a cleaner scrolling activity stream, dynamic total/per-game open counts, and restored PWA installation.

Inventory: GR crown/shield and metallic blue logo; existing purple/cyan player identity; Orbitron; owned game montage; real catalog banners; immutable tournament occurrences; existing registrations/start times; actual completed match winners; weekly computed results; eligibility/consent-based registration; existing install prompt/manual-help PWA lifecycle.

Page argument: join the competition -> cinematic hero and real featured event; see availability now -> uncapped aggregate counts; see the platform move -> compact truthful results; choose a game -> real image gallery; earn recognition -> computed weekly standings; understand the flow -> existing steps; trust the mechanism -> real stats, features and moderated reviews.

Page direction: name-as-monument, seed 88616077, adapted to the user's explicit web3 request. The full-width cinematic arena carries the existing competitive headline; the name returns at poster scale in the footer. Cyan/amethyst depth, thin luminous edges, low-opacity glass and brand gradients are intentional user-requested exceptions to the skill's flat-page house rules. Do not interpret a generic house prohibition as overriding the user's chosen aesthetic. Limits: effects are confined to guest surfaces, not authenticated dashboards; body copy stays high-contrast; motion respects reduced motion; no fake cryptocurrency, wallet-connect or made-up platform numbers.

Live thing: truthful total and per-game open counts rotate and poll every 30 seconds. The aggregate is independent of the four visible tournament cards. The cleaner activity stream lives under the hero, with initial avatars, short actions and context; pause/focus/reduced-motion remain available. PWA install is visible in the header and mobile menu and uses the existing browser-native/manual-help flow.

Auth direction: true-black-flat, seed 314100573, adapted to the same brand on the surrounding story panel. Questions: login = how do I get back into my player account? Registration = what is needed to create my eligible player identity? The form remains quiet, with explicit labels, password visibility, loading/error states, original validation, legal/age consent and optional newsletter unchanged. The story art drops out on mobile so the form is first.

Assets: existing project footage, its captured poster, original GR marks and real game catalog images only. Nothing was taken from the staging site's assets or copy. Local test data remains local data; no new invented winners or activity were added.

ROUND 1
Seen in one second: a full-width arena with the competitive headline and an actual tournament entry panel.
Idea (one sentence): GamersRival's crowned identity becomes a cinematic player portal, with real competition as the primary interaction.
Live thing: uncapped open totals and per-game rotations, plus a clean public results stream.
Layout words by section: hero=cinematic-arena / schedule=entry-list / games=cover-gallery / honours=weekly-sheet / how=sequence / stats=ambient-ledger / features=menu / reviews=quote-with-author / footer=brand-monument
Scores: design 8 · usability 8 · creativity 7 · content 7.5 → overall 7.8
Caps applied: original meter flagged small text, a decorative background wordmark and icon-card false positives in the schedule; these were addressed before final inspection.
Top three problems (most visible first), each with screenshot and fix:
1. web3-home-390: the constrained mobile title became four lines. Remove the mobile width constraint and keep the headline on two lines.
2. initial full-page review: 12px metadata and 14px running copy were too small. Raise metadata to at least 13px and running paragraphs to 16px; retain compact form labels.
3. initial home inspector: a huge initial for a game with no artwork looked like placeholder design. Use the actual GR brand cover for that catalog entry, and remove the faint hero backdrop wordmark.

Functional evidence: 46 focused CMS/login/registration/two-factor tests passed (212 assertions). Live browser checked sum of real per-game counts against the total (6 = 4 + 2 in this local dataset), rotation, results motion/pause, PWA manual help and native prompt/installed state wiring, visible mobile-menu install, registration strength/mismatch/consent gating, optional newsletter remaining unchecked, keyboard-accessible password visibility, an actual login server error response, reduced motion and zero mobile page overflow. No registration was submitted in the browser.

Static analysis: new competition read model, pulse component and registration render pass scoped PHPStan. Login's unchanged lockout block has two existing always-false warnings at lines 95–96; analyzing the HEAD version at /tmp/GuestBaselineLogin.php reproduces the same two warnings. Only the layout selector changed in Login; do not alter authentication protection to silence unrelated analysis.

Source lint reports deliberate web3 gradients/elevation, quiet interface outlines and separators. Those exact style families are allowed in rendered checks per the user's new brief. The 100vh warning was fixed to 100svh. No whole-page UI exemptions or placeholder-data exceptions are used. The visual harness suppresses only the existing service-worker first-install reload in its own browser so it cannot interrupt the probe; production PWA behavior remains unchanged.

ROUND 2 — final reviewed implementation
Seen in one second: a large competitive headline against GamersRival's own footage, answered by a real tournament entry panel.
Idea (one sentence): the crowned GR player identity frames an active competitive arena, from first entry through player account access.
Live thing: actual aggregate availability and per-game rotation; recent public tournament statuses and completed-match winners scroll beneath the arena.
Layout words by section: hero=cinematic-arena / schedule=entry-list / games=cover-gallery / honours=weekly-sheet / how=sequence / stats=ambient-ledger / features=menu / reviews=quote-with-author / footer=brand-monument / login=story-and-form / register=story-and-form
Scores: design 8 · usability 8 · creativity 7 · content 7 → raw overall 7.7
Caps applied: the skill's literal no-gradient/no-shadow/no-hairline rubric caps this at 6.5. Those effects deliberately follow the user's explicit web3 direction; the raw score above describes the requested aesthetic, not compliance with that flat-design rubric. Creativity remains capped at 7 because cinematic gaming is a known approach, despite using original GR assets.
Top three remaining limitations (most visible first), each with screenshot and disposition:
1. reviewed-home/mobile-page-01.jpg: the hero and real featured tournament occupy more than one mobile screen. Retained to keep actionable event details and readable copy; the first-screen Explore button remains visible.
2. reviewed-home/desktop-page-01.jpg: the catalog repeats bordered game covers. Retained as a coherent game-selection collection; adjacent schedule, honours and steps use distinct compositions. This is one rendered inspector warning.
3. reviewed-home/desktop-fold.jpg: display type, compact tournament metadata and section headings use more than five computed sizes. Retained for the responsive brand hierarchy; running paragraphs remain 16px. This is the other rendered warning.

Final evidence:
- Final homepage inspection: /tmp/web3-reviewed-home, 1440/768/390, 0 FAIL / 2 WARN. Opened every listed fold and page sheet after the final CSS and counter-focus changes.
- Login and register: /tmp/web3-final-login and /tmp/web3-final-register, each 1440/768/390, 0 FAIL / 1 WARN (brand-art colour variety). Opened all six images per page. No auth-source changes followed their inspection.
- Compared against /tmp/web3-before-home-1440.jpg and /tmp/web3-before-home-390.jpg: replaced the flat split and two title-heavy rails with full-width media, concise availability and a separate clean results stream. Account pages now share the GR identity and become form-first below desktop.
- Final browser behavior rerun passes total/game reconciliation, 5-second rotation and focus pause, horizontal stream and pause, visible mobile install, manual install help and synthetic browser install lifecycle, mobile menu/Escape, registration consent/strength/mismatch behavior, keyboard password visibility, server-returned login error, reduced motion and zero mobile overflow.
- Build passes; 46 focused tests / 212 assertions pass; targeted Pint and diff whitespace checks pass. New read-model/Pulse/Register PHPStan passes; the two baseline Login warnings remain as described above. Full repository test suite was not run.
- No new dependencies, migrations, fake activity or external stock assets. One catalog game has no artwork and uses the real GR mark. Existing local tournament titles/reviews are rendered unchanged.
- PWA native prompt wiring is tested by synthetic browser events, not a physical-device installation. Availability/status feed refreshes every 30 seconds while visible rather than subscribing to new backend broadcasts. Public wallet activity is deliberately absent because the existing data does not establish public-display consent.
- Branch: feat/guest-page-redesign. Existing unrelated admin seeder/test edits preserved. No commit, merge or deployment.
