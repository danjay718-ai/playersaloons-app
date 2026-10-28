Inventory: PlayerSaloons repository; current public brand GamersRival; Inter/Orbitron; cyan identity; owned competitive montage compressed_v1.mp4; CMS game banners; tournament occurrences and start times; check-in lifecycle; match wins; rolling seven-day player standings; CMS-reviewed community comments; CMS navigation/legal links.
Argument: competition is happening -> actual schedule; find a game -> catalog artwork; earn your place -> weekly results; join confidently -> existing workflow; activity is real -> computed stats; tools support competition -> existing features; community proof -> moderated reviews.
Direction: page-is-the-object, seed 430328258. Idea: GamersRival's tournament occurrence schedule and weekly results become a tabbed competition programme. Live thing: tournament board and rolling public results.
Brand constraint: retain actual deployed GamersRival identity, cyan, Inter and Orbitron. Existing CMS copy retained. No stock images, no invented activity or winners.
Copy deck: existing CMS titles/body and calls to action, with On the board / Weekly honours / Latest activity / Your next match awaits. Copy stored in en locale.


ROUND 1
Seen in one second: direct competitive promise, backed by the tournament programme.
Idea (one sentence): GamersRival's schedule and weekly results become a competition programme.
Live thing: public tournament board, rotating results and game artwork preview.
Layout words by section: hero=tabbed programme / games=index-preview / honours=result-sheet / how=sequence / stats=ledger / features=menu / reviews=slips / footer=programme-back
Scores: design 7.5 · usability 7 · creativity 7 · content 7.5 → overall 7.3
Caps applied: inspector FAIL caps overall at 6.0 for this round.
Top three problems (most visible first), each with the screenshot it shows in and the fix:
1. mobile-fold: ticker starts mid-sentence and pause CSS loses specificity. Replace sideways travel with rotating complete updates.
2. desktop-page-02: how/stats/features repeat the split composition. Give stats its own full-width ledger field.
3. mobile-page-01: weekly empty-state eyebrow and video blank under reduced motion. Keep only date context and extract a poster from the owned video.
Brand exception: gradient-text is retained only in the established public wordmark asset; inspect uses --allow gradient-text for this exact brand constraint.


ROUND 2
Seen in one second: tournament programme and strong competitive headline.
Idea (one sentence): the platform's schedule and weekly results become a tabbed competition programme.
Live thing: rotating public activity, real registration board and pointer/keyboard game preview.
Layout words by section: hero=programme / games=index-preview / honours=result-sheet / how=sequence / stats=full-width-ledger / features=menu / reviews=slips / footer=programme-back
Scores: design 7.5 · usability 8 · creativity 7 · content 7.5 → overall 7.6
Caps applied: the last meter still flags title arrival opacity and a standalone activity timestamp above the title; both corrected for the final meter.
Top three problems (most visible first), each with the screenshot it shows in and the fix:
1. mobile-fold: a standalone timestamp reads like a label for the hero. Join event and relative time into one readable sentence.
2. desktop-fold: load-time headline opacity can remain low on a slow paint. Keep the title fully opaque and animate position only.
3. tablet-page-01: catalog/feature copy remains long in the existing CMS. Preserve content; leave editorial shortening to content owners.

Implementation: GuestCompetitionService reads existing public Tournament occurrences and completed GameMatch winners. It never reads deposits or wallet records. Eight activity records, four tournament entries, 30-second Livewire visible polling, six-second local rotation with pause and hover/focus hold. Only the small pulse polls. Weekly honours keep the existing computed seven-day leaderboard. All existing CMS section visibility, text, destinations, game artwork and approved reviews are retained. Existing authenticated home/nav/footer render unchanged.
Assets: existing game catalog artwork and compressed_v1.mp4 only; public/images/guest-highlights.webp is a captured frame of that same video. No found stock photography, external clips, generated imagery, invented winners or demo activity were added. No new asset credits are required. Existing local database test tournament names/review text visible in screenshots remain environment-owned content.
Checks: Vite production build; focused CMS tests (15 tests, 87 assertions); authenticated public-navigation regression (1 test, 8 assertions); scoped Pint; scoped PHPStan (0 errors); source taste lint (0 FAIL, 0 WARN).
Remaining measurement warnings: size-count includes two established brand marks, title, section heading, result figure, body and metadata roles; preserving the distinct brand and results hierarchy justifies these scales. The one side-scroll rail is the intentional compact tournament strip, with native touch/keyboard scrolling and a persistent schedule link; the results ticker itself no longer scrolls sideways.


ROUND 3 — FINAL
Seen in one second: the competitive headline; real tournaments immediately back it up.
Idea (one sentence): GamersRival's live schedule and rolling seven-day results become a tabbed competition programme.
Live thing: public results rotate every six seconds, refresh from real records every thirty seconds, and can be paused; the game index previews real artwork on pointer/focus.
Layout words by section: hero=tabbed-programme / games=index-preview / honours=results-sheet / how=sequence / stats=ledger-band / features=menu / reviews=slips / footer=programme-back
Scores: design 7.5 · usability 8 · creativity 7 · content 7.5 → overall 7.6
Caps applied: none. Existing gradient-text brand exception documented above.
Top three problems (most visible first), each with the screenshot it shows in and the fix:
1. desktop-fold/mobile-fold: seeded local tournament titles are long and include test names; retain actual records rather than fabricate replacements. Content owners should clean environment data before public launch.
2. tablet-page-01: game and feature descriptions remain editorially long; CMS copy is preserved intentionally.
3. mobile-page-01: a game without artwork has a text-only index entry; add actual catalog art through CMS when available.

Final measurements: /tmp/guest-release/report.json. Desktop 1440, tablet 768, mobile 390: 0 FAIL, 2 WARN. All nine listed screenshots opened and reviewed. Headline is two lines at all widths; zero page overflow. Before: 17 FAIL and 7 WARN; after: 0 FAIL and 2 WARN. Source lint: 0 FAIL, 0 WARN.
Browser assertions: menu expansion, Escape close, activity paused across a 6.5-second interval, resume advancing updates, visible keyboard focus, reduced-motion video paused with poster, and public destinations /tournaments, /h2h?view=guest, /about, /contact, /policies, /register, /login all passed. Script: /tmp/guest-interactions.mjs; results: /tmp/guest-interactions.log.
Additional checks: contact/language tests 12 passed (48 assertions); signed-in public navigation regression 1 passed (8 assertions). Together with focused CMS coverage: 28 tests, 143 assertions. Full application suite was not run. No migrations, new packages, commits, merges or deployment.
