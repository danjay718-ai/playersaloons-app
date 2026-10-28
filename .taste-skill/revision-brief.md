# Revision: GamersRival arena broadcast

User correction: the first programme design feels too plain. The reference at https://staging.playersaloons.com/ clarifies a continuous horizontal news ticker, rather than a single fading update. Retain GamersRival's own visual identity and composition.

Inventory: actual GR crown/shield, blue-and-silver logo, Orbitron lettering, cyan action color, dark player platform, owned game montage, game catalog banners, occurrence schedule, current match winners, computed weekly standings, CMS copy and approved reviews.

Argument: join a competition -> prominent existing CTA with real game footage; see the arena move -> truthful scrolling newswire; pick a real event -> image-led schedule; find your game -> preview index; earn recognition -> real weekly honours; understand entry -> existing steps; see proof -> computed stats and moderated reviews.

Direction draw: The pinned poster, seed 703623399. The initial sentence-as-layout draw (335628028) required font-demo controls, which are unrelated to tournament participation and contradict the user's product scope. The pinned-poster concept is adapted to the user's stronger branding request: a crest-framed video hero and a pinned feature heading, rather than splitting the GamersRival name across unrelated scroll screens. No stock architecture/coast imagery or generic typography controls were introduced.

Idea: On this page, GamersRival's silver-and-blue crowned shield becomes the framing and broadcast signature for its actual games and results. Chamfered media/button corners echo the crest; Orbitron and the blue Rival wordmark stay consistent from topbar through footer. The live thing is the Rival wire: a genuinely continuous horizontal stream of public results, not a timed slide replacement.

Motion: two equal-width news groups, one inaccessible inert visual repeat, translated exactly half the track for a seamless loop. Explicit pause, hover/focus pause, reduced-motion static list, and a plain native-scroll list before JavaScript initializes. The data source, limit of eight, 30-second visible polling and exclusion of private financial activity are unchanged. No interval timer or dependency is needed for the ticker.

Assets: existing /compressed_v1.mp4, its captured poster /images/guest-highlights.webp, /gamersrival-logo.webp and existing CMS game banners. No staging imagery, copy, layout code or fabricated results were copied.

ROUND 1
Seen in one second: the competitive title and large game footage carrying the actual GR crest.
Idea (one sentence): the crowned GR shield frames a broadcast of real competition.
Live thing: horizontally scrolling public results with pause and genuine game/tournament records.
Layout words by section: hero=cinematic-split / tournaments=image-led-schedule / games=preview-index / honours=result-sheet / how=sequence / stats=cyan-field / features=pinned-menu / reviews=slips / footer=Orbitron-wordmark
Scores: design 7.5 · usability 8 · creativity 7 · content 7.5 → overall 7.6
Caps applied: none assessed until final responsive inspection completes.
Top three problems (most visible first), each with the screenshot it shows in and the fix:
1. desktop-fold: previous hero made the footage secondary. It now occupies half the stage and carries the real GamersRival mark.
2. desktop-page-01: dark sections were too similar. A cyan stats field creates a strong branded change of pace.
3. desktop-page-02: generic footer lettering weakened the brand. It now uses Orbitron and a cyan Rival, matching the actual identity.

The first inspection was interrupted by Vite's hot reload while production assets rebuilt; it is not counted as a passed check. Final inspection is rerun after source/build changes settle.


ROUND 2
Seen in one second: game footage framed by the GR crest, with the broadcast headline alongside.
Idea (one sentence): the crowned GR shield frames a broadcast of real games and competition.
Live thing: a seamless horizontal newswire of public events, with paused and static alternatives.
Layout words by section: hero=cinematic-split / tournaments=image-led-schedule / games=preview-index / honours=result-sheet / how=sequence / stats=cyan-field / features=pinned-menu / reviews=slips / footer=Orbitron-wordmark
Scores: design 7.5 · usability 8 · creativity 7 · content 7.5 → overall 7.6
Caps applied: none; final stable meter returned 0 FAIL and 2 WARN before the final tablet refinement.
Top three problems (most visible first), each with the screenshot it shows in and the fix:
1. tablet-page-01: the preview plus thumbnails squeezed catalog copy into a narrow column. Keep the dominant preview and hide duplicate thumbnails at tablet widths.
2. tablet-page-01: four step columns forced tall text stacks. Use two columns on tablet and one on phone.
3. mobile-fold: the reduced-motion fallback could become a tall header. Cap its scrollable list at 160px; keep full records readable without horizontal movement.

Verification details: 15 CMS tests passed (87 assertions), production Vite build passed, scoped Pint passed, source taste lint 0 FAIL/0 WARN. The dedicated browser test validates actual transform movement, pause/resume, keyboard hold, inaccessible inert duplicate, no page overflow and a static reduced-motion list. It waits for Alpine initialization before measuring the two equal loop groups.
The screenshot harness disables only the pre-existing first-install service-worker reload in its test browser; otherwise that reload can interrupt the visual probe. Application PWA behavior is unchanged.
Remaining warnings are the existing branded/results type scales and intentional native horizontal tournament rail. The real newswire has its own narrowly scoped data-ui root because it is an interactive activity interface, with a pause control and focusable scroll region.

ROUND 3 — FINAL
Seen in one second: the GamersRival title and large crest-marked game footage.
Idea (one sentence): the GR crowned shield supplies the frame for a broadcast of real competition.
Live thing: the Rival wire continuously scrolls genuine public results and tournament states; it has pause, hover/focus hold, reduced-motion and plain native-scroll alternatives.
Layout words by section: hero=cinematic-split / tournaments=image-led-schedule / games=preview-index / honours=result-sheet / how=responsive-sequence / stats=cyan-field / features=pinned-menu / reviews=slips / footer=Orbitron-wordmark
Scores: design 7.5 · usability 8 · creativity 7 · content 7.5 → overall 7.6
Caps applied: none. The only gradient-text allowance is the unchanged existing header wordmark.
Top three problems (most visible first), each with the screenshot it shows in and the fix:
1. desktop-fold: local tournament titles include test fixture names. Real records remain visible; no fabricated replacement activity was added.
2. mobile-page-01: one catalog game has no supplied artwork. Its index uses actual game text; the desktop preview now has a typographic fallback rather than a blank panel.
3. mobile-page-01: the weekly board has no current local winners. Keep the truthful empty state until actual completed matches produce standings.

Final responsive inspection: /tmp/guest-arena-reviewed/report.json — 0 FAIL, 2 WARN at 1440, 768 and 390. All eight listed final screenshots opened and reviewed after the last CSS change. Compared with the prior guest programme, the hero footage is promoted into the first-screen composition, the ticker genuinely moves horizontally, the table has real game artwork, and the footer uses the actual brand type and color treatment. No new sample claims or proof have been added.
Branch: feat/guest-page-redesign. No new dependencies, migrations, commits, merges or deployment. Full application suite not rerun for this presentation revision; focused tests and browser behavior checks cover the affected area.
Final browser assertions passed: continuous transform movement, equal loop widths within subpixel rendering tolerance, a loop group wider than the viewport, pause/resume, keyboard hold, inert duplicate, reduced-motion static list capped at 160px, visible mobile title, mobile menu and no page overflow. Results: /tmp/guest-newswire-check.log. This UI check uses the same service-worker isolation as the visual harness; PWA lifecycle behavior is outside this revision.
