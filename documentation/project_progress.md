# PlayerSaloons — MVP Progress

**Last Updated**: 2026-08-19 (v1.116) | **Branch**: `main`

---
## ✅ Centralized Error Operations & Automatic PWA Releases (v1.116)

- **Formal error boundary**: Global HTTP/Livewire/API exception rendering now returns sanitized 403/404/419/500 copy. Reportable 403/500 incidents receive support references; raw exception messages were removed from error pages and broad Livewire catches.
- **Incident recorder**: Added privacy-aware `error_incidents` persistence with 15-minute fingerprint grouping, occurrence counts, hashed IPs, safe context allowlisting, SQL/query-binding omission, normal Laravel-log fallback, and 90-day scheduled pruning.
- **Async coverage**: Queue and scheduler failure listeners attach safe job/task context to centralized incidents.
- **Admin Error Logs**: Added ADMIN/SUPER_ADMIN-only `/admin/error-logs`, consistent with Tournament Management filtering/table/pagination. Supports search, status/level/source/date filters, details, stack traces, resolution notes, and reopen.
- **PWA releases**: Vite now generates `public/sw.js` and `public/pwa-version.json` from a content-derived release hash. Waiting updates show a shared **Update now** prompt and activate only after confirmation.
- **Verification scope**: Focused incident tests cover public redaction/reference behavior, grouping, secret removal, RBAC, and resolution. Frontend production build verifies service-worker generation. No full regression suite was requested.

---
## ✅ Platform H2H, Recurrence, Lifecycle, and Player Performance Hardening (v1.115)

- **Platform-created H2H**: The admin competition form now creates either a tournament or a fixed 1v1 head-to-head occurrence. Domain actions enforce the 2-player solo invariant independently of Livewire.
- **Future wager preservation**: Player-created wagers remain in the codebase behind `PLAYER_WAGER_ENABLED=false`; their route, navigation, prompt, and expiry job are off by default, and the global prompt no longer polls.
- **Reliable lifecycle**: One indexed, chunked reconciler now catches overdue competitions through registration, check-in, bracket generation, start, and opted-in underfill cancellation every minute.
- **Reliable recurrence**: Daily, weekly, and monthly templates are timezone-aware, atomically create their first occurrence, use row locks and a unique occurrence key, and generate every five minutes with a per-template catch-up cap.
- **Scale improvements**: Added lifecycle/integrity indexes, replaced check-in N+1 writes with set-based operations, removed duplicate detail queries and Blade-side stream queries, and replaced Redis key scans/per-user presence calls with a bounded sorted set.
- **Local parity**: Local application defaults now use MySQL and Redis through Docker Compose; the isolated test suite continues to use in-memory SQLite.
- **Verification**: New competition scheduling tests cover H2H invariants, lifecycle catch-up/cancellation, recurrence idempotency, and monthly day clamping. Fresh migration, scheduler registration, route gating, Blade compilation, and focused suites were verified.

---
## ✅ Expanded Locale Translation Coverage (v1.114)

- **Runtime locale files**: Replaced the remaining English fallback-heavy values across German, Spanish, French, Italian, Japanese, Dutch, Polish, Portuguese, Russian, and Chinese JSON catalogs with localized copy.
- **Catalog parity**: All ten non-English runtime catalogs contain the same 82 phrase keys as `lang/en.json`, with no missing or extra keys.
- **Scope**: Updated translation data only; locale selection, middleware, admin translation management, and the supported-language list are unchanged.

---
## ✅ Admin Matches UI, Tournament Automation, Roles & Admin Polish (v1.113)

- **Tournament Automation**: Added `AutoCancelTournaments` to automatically cancel/refund underfilled tournaments before they start. Added `AutoGenerateRecurringTournaments` to generate daily/weekly/monthly tournaments from templates. Both registered in Laravel Scheduler.
- **Admin Tournament Matches UI**: Added `TournamentMatches` component (Fixtures style layout) to view all matches for a tournament, supporting both team logos and individual profiles/initials, and status filtering.
- **H2H Commission**: Added a system setting for H2H commission percentage and wired it into `ResolveHeadToHeadStakeAction` to deduct a house cut from the total match pool before payout.
- **Roles & Permissions UI**: Completely redesigned the Roles and Permissions admin page with a modern, responsive layout, intuitive grouping, toggle switches, and added it to the sidebar.
- **About Us CMS**: Added an About Us page into the CMS Navigation management for dynamic content editing.
- **Admin Modal Latency Fix**: Applied the instant AlpineJS state hiding trick (`@click="open = false"`) across Admin modals (UserAdmin, TournamentForm, etc.) to eliminate perceived Livewire round-trip latency.

---
## ✅ Geo-Blocking & Localization UI Updates (v1.111–v1.112)

- **Localization Updates (v1.111)**:
  - Swapped unicode emoji flags for `flag-icons` CSS classes via CDN across all major layout files.
  - Added visibility toggles in **System Settings** for the Language Switcher, allowing admins to selectively hide it on guest and admin pages (defaults to hidden).
  - The language switcher remains visible by default in the player dashboard.
- **Geo-Blocking (v1.112)**:
  - Integrated `stevebauman/location` for IP geolocation.
  - Created `blocked_countries` table and `BlockedCountry` model to persist ISO country codes, display names, and customizable restriction messages.
  - Implemented `BlockRestrictedCountries` middleware applied globally (except for admin/system paths) to intercept users from blocked regions.
  - Added a Geo-Blocking UI panel (`/admin/geo-blocking`) allowing admins to quickly add, edit, or remove blocks and restriction messages.

---
## ✅ Team Tournaments, Timing Default, and Rematch Voting (v1.110)

- **Team tournaments**: Captains can register active teams with sufficient rosters; registration snapshots the competing roster, creates team-aware participant slots, and grants roster members tournament and match access under existing permissions.
- **Auto-forfeit configuration**: Admins can set the default result-confirmation timeout in System Settings, and new tournaments inherit it while retaining their per-tournament override.
- **Mutual rematches**: Match participants can cast expiring, side-aware rematch votes before a dispute. Agreement from both sides closes the original without a winner and creates a ready replacement match.
- **Verification**: Focused tournament, match, and settings suites pass with 18 tests and 66 assertions.

---
## ✅ Instant Player Review Star Selection (v1.109)

- **Review UX**: Replaced per-star Livewire requests with deferred Alpine state, so rating clicks update immediately and synchronize only when the form submits.
- **Accessibility**: Added radio-group semantics, selected-state ARIA values, keyboard focus styling, and a visible numeric rating summary.
- **Verification**: All 5 player review tests pass with 14 assertions; the production frontend build succeeds.

---
## ✅ Player Mobile Navigation Variable Fix (v1.108)

- **Dashboard layout**: Moved `$bottomNavItems` initialization to the top-level layout scope so cached Blade rendering always defines it before the mobile navigation loop.
- **Regression coverage**: Added a direct authenticated `/reviews` request assertion for the mobile bottom navigation and Review Us entry.
- **Verification**: Cleared and rebuilt the Blade view cache; all 5 player review/navigation tests pass with 12 assertions.

---
## ✅ Player Reviews & Star Ratings (v1.107)

- **Player flow**: Added `/reviews` where each authenticated player can submit or edit one 1–5 star platform review.
- **Moderation**: New and edited reviews remain pending until ADMIN/SUPER_ADMIN approves or rejects them at `/admin/player-reviews`.
- **Public display**: Only approved player reviews appear in the landing-page reviews section with star ratings and player identity.
- **Navigation**: Added Review Us to player navigation and Player Reviews to admin navigation.
- **Tests**: Review and landing coverage passes with 8 tests and 35 assertions.

---
## ✅ Advertisement & Promotion Management (v1.106)

- **Admin**: Added `/admin/advertisements` for ADMIN/SUPER_ADMIN to create, edit, schedule, activate, and delete promotions with optional image/destination URLs.
- **Player placement**: The shared authenticated player layout displays the latest currently active promotion as a dismissible sponsored banner.
- **Scheduling**: Inactive, future, and expired promotions remain hidden.
- **Tracking**: Promotion destination links redirect safely through a click-counting route and use sponsored/no-opener link attributes.
- **Tests**: All 4 focused advertisement tests pass with 9 assertions.

---
## ✅ Dynamic Deposit Processing Fees (v1.105)

- **Admin settings**: Added clearly described enable, fixed-fee, and percentage-fee controls under `/admin/system-settings`.
- **Player transparency**: Wallet deposits show wallet credit, processing fee, and total Stripe charge before checkout.
- **Stripe**: Checkout charges credit plus fee; signed Stripe metadata preserves the intended wallet credit and fee separately.
- **Webhook security**: Fulfillment verifies charged total equals credit plus fee, credits only the requested wallet amount, and persists `deposits.fee_amount`.
- **Tests**: 35 focused wallet tests pass with 79 assertions.

---
## ✅ Referral Deposit Qualification (v1.104)

- **Qualification policy**: Changed referral rewards from email verification to the referred player’s first successfully processed deposit.
- **Integration**: `QualifyReferralOnDepositListener` reacts only to `DEPOSIT` wallet credits; duplicate deposit webhooks and later deposits cannot duplicate a rewarded referral.
- **Settings**: Dynamic reward values are read when the qualifying deposit completes.

---
## ✅ Dynamic Referral Rewards (v1.103)

- **Attribution**: Registration captures valid active referrers from the existing integer `?ref={user_id}` links in an auditable, unique referral record.
- **Qualification**: Initially implemented for verified email, then changed to first successful deposit in v1.104.
- **Wallets**: Referrer and new-player bonuses use immutable `REFERRAL_BONUS` ledger entries with row locking and rewarded-state idempotency.
- **Dynamic settings**: ADMIN/SUPER_ADMIN can enable referrals and adjust both reward amounts at `/admin/system-settings`; values are read at qualification time and store `updated_by`.
- **Profile**: Referral cards show rewarded, pending, and total earned values.
- **Tests**: All 5 focused referral tests pass with 12 assertions.
- **PHPStan**: Focused referral analysis passes.

---
## ✅ Newsletter Management (v1.102)

- **Audience**: Added `/admin/newsletters` with searchable active, verified newsletter subscribers sourced from registration opt-ins.
- **Campaigns**: Added persisted campaign records with creator, recipient, sent, failed, status, and sent-at audit fields.
- **Sending**: Admins can send plain-text campaign content through the configured Laravel mail failover; delivery failures are isolated, counted, and logged.
- **Unsubscribe**: Every campaign email includes a signed per-user unsubscribe link that removes the recipient from future audiences.
- **Authorization**: Newsletter management is restricted to ADMIN and SUPER_ADMIN staff.
- **Tests**: All 5 focused newsletter tests pass with 19 assertions.
- **PHPStan**: Focused analysis passes for the newsletter component, model, mailable, and tests.

---
## ✅ Contact Inquiry Workflow Polish (v1.101)

- **Admin inbox**: Added live New, In Review, Resolved, and Archived counters that also act as status filters.
- **Support UX**: Added distinct category/status badges and a prefilled reply-by-email shortcut for the selected inquiry.
- **Dashboard**: Added a Contact Inquiries summary counting new and in-review messages with direct inbox navigation.
- **Tests**: All 7 focused contact inquiry tests pass with 27 assertions.
- **PHPStan**: Focused analysis passes for the modified components and tests.

---
## ✅ Documentation Backlog Synchronization (v1.100)

- **Backlog**: Consolidated the next implementation sequence in `execution_checklist.md`: contact inquiry workflow, newsletter management, referral logic, and deposit processing fees.
- **Feature gaps**: Queued team tournaments, auto-forfeit timeout settings, and rematch voting after the four prioritized features.
- **Production**: Moved external payout provider integration to the production-readiness checklist while sandbox testing continues with manual payouts.
- **Cleanup**: Removed stale pending labels for tests completed in v1.32 and v1.98.

---
## ✅ Compliance, 2FA, ELO, and Provider Status (v1.99)

- **Compliance**: Added auditable expiring/revocable player blocks, self-authorizing apply/revoke actions, authenticated-route enforcement, and `/admin/compliance` management.
- **Two-factor authentication**: Added encrypted TOTP secrets, hashed single-use recovery codes, profile setup/disable controls, and a pending-login challenge that prevents password-only session completion.
- **H2H ELO**: Added per-game 1200-baseline ratings, K-factor 32 updates for confirmed/adjudicated winners, match-level idempotency and rating snapshots, plus a skill window that expands as challenges wait.
- **Provider status**: Added optional YouTube, Twitch, and Facebook API detection, persisted check/error state, and a two-minute queued refresh schedule. Missing credentials and provider failures preserve manual status.
- **Tests**: 36 focused tests pass with 125 assertions. PHPStan still exits with code 1 and no diagnostics/output in this environment.

---
## ✅ Player Tournament Livewire Coverage (v1.98)

- **Elimination modal**: Added coverage for lost and active player state, plus the Go Back and Continue client transitions on the Matches tab.
- **My Tournaments**: Added aggregate assertions for active/history and win/loss metrics, verified eliminated players move from Active to History, and guarded the batched match-history query against N+1 regressions.
- **Browse filters**: Added combined coverage for search, game, tournament status, and frequency filtering.
- **Verification**: All eight focused component tests pass.

---
## ✅ Broadcast Socket ID Hardening (v1.97)

- **`SanitizeBroadcastSocketId`**: Added web middleware that removes malformed `X-Socket-ID` headers such as `undefined` before Laravel/Reverb/Pusher broadcast code reads them, preventing `Invalid socket ID undefined` 500 errors after Echo is initialized but not connected yet.
- **Chat broadcasts**: Removed `toOthers()` from global chat and stream chat broadcast dispatches so sending messages no longer depends on the browser providing a valid socket id. Global chat already deduplicates messages by UUID; stream chat now ignores duplicate message ids on the client.
- **`StreamWatch`**: Wrapped stream chat, stream message deletion, and viewer-count broadcasts with warning-level logging so realtime broadcast failures no longer block stream chat persistence or moderation actions.
- **Regression coverage**: Added a stream chat integration test that sends through Livewire with `X-Socket-ID: undefined` and verifies the message persists without Livewire errors. Focused chat tests and the new socket regression pass. PHPStan still exits with code 1 and no diagnostics/output in this environment.

---
## ✅ Chat Broadcast Failure Resilience (v1.96)

- **`ChatService`**: Moved realtime broadcast dispatch outside the chat message database transaction and guarded it with warning-level logging, so a down Reverb/Pusher service no longer makes `/chat` sends fail after the message is saved.
- **Regression coverage**: Added a chat integration test that forces the realtime broadcaster to be unavailable and verifies the send endpoint still returns `201 Created` and persists the message.

---
## ✅ Realtime Global, Direct, and Team Chat (v1.95)

- **Persisted chat pipeline**: Replaced the `/chat` mock with `chat_conversations`, `chat_participants`, and `chat_messages` for global, player-to-player, and team conversations.
- **Realtime delivery**: Added private Reverb broadcasting through `ChatMessageSent` on `chat.{conversationUuid}`, authorized by verified global access, direct participants, or active team membership.
- **Optimized UI**: Rebuilt `/chat` as an Alpine-driven game-style comms console that uses small JSON endpoints for message history/send actions instead of Livewire polling; chat JS now lives in the compiled app bundle so Livewire navigation does not leave the page stuck syncing.
- **Direct and team channels**: Players can search usernames, open profile stat modals from search or chat messages, follow players, start direct chats, and open team channels for teams they actively belong to.
- **Unread/team UX**: Chat channels now show a New badge while unread, messages render player avatars/initials, and team channels can be joined from chat with a warning that switching channels changes the player's active team membership.
- **Global retention**: Global chat automatically prunes older messages after the latest 100, keeping the public channel bounded.
- **Security**: Message APIs expose player-safe fields only, enforce participant/team membership access, validate message length, and keep unauthenticated/unverified users behind existing route middleware.
- **Tests/build**: Added `tests/Feature/Community/ChatIntegrationTest.php`; focused chat tests and `npm run build` pass.

---
## ✅ Player Sidebar Viewport Pinning Fix (v1.94)

- **`resources/views/components/layouts/dashboard.blade.php`**: Changed the desktop player sidebar from `sticky` to viewport `fixed` positioning so its bottom action area stays pinned to the viewport while long player content scrolls.
- **Content offset**: Added a desktop-only `md:pl-20` offset to the player content pane, matching the collapsed sidebar width and preventing content from sliding underneath the fixed sidebar.
- **Build**: `npm run build` passes.

---
## ✅ Real-time Stream Chat & Twitch-Style Redesign (v1.92)

- **Twitch-Style Redesign:** Overhauled the `/streams` page with a Twitch-inspired UI. Added an Alpine.js carousel for featured streams and a browse section with game categories.
- **YouTube-Style Watch Page:** Redesigned the `/streams/{id}` watch page to match a YouTube/Twitch viewing experience, handling mobile responsiveness cleanly.
- **Admin Isolation:** Secured the admin viewing experience via a dedicated `/admin/streams/{id}` route, preventing player sidebars from bleeding into the admin UI.
- **Real-Time WebSockets (Reverb):** Migrated stream chat and viewer counts from `wire:poll` to **Laravel Reverb**. Implemented `StreamMessageSent`, `StreamMessageDeleted`, and `StreamViewerCountUpdated` broadcast events for instant updates.
- **Advanced Admin Moderation:** Expanded admin stream controls to include View Stream, Take Down, Restore, Feature, Mark Live, Delete Stream, Delete Chat Messages, and **Mute User**.
- **Moderator Chat Experience:** Allowed moderators to chat while maintaining moderation abilities. Added "Emerald Green" moderator badges and message highlighting.
- **Fixes:** Addressed an `ArgumentCountError` caused by malformed Blade directives (`@livewire-event`) and cleaned up duplicate login prompts in the chat.

---
## ✅ Player and Tournament Stream Embed Integration (v1.91)

- **Normalized stream storage**: Added `stream_channels` as the shared stream model for player-owned and tournament-owned broadcasts, keeping providers extensible for future embeds and rewards.
- **Player streams**: Players can publish YouTube, Twitch, or Facebook Live stream channels directly from `/streams` without attaching them to H2H or tournament flow.
- **Game trailers**: Added sample YouTube trailer stream channels for the five default seeded games and a Game Trailers section on `/streams`.
- **Admin moderation**: Added `/admin/streams` for staff to view player streams, take down invalid/abusive streams with a reason, and restore streams after review.
- **Tournament stream fields**: Added optional YouTube, Twitch, and Facebook stream URLs to tournaments and wired them into the admin tournament wizard with provider-specific HTTPS validation.
- **Audit logging**: `StreamChannel` uses model-level activity logging for create/update/delete writes, with extra action context for admin takedown/restore. Stream reads are not logged.
- **Embedded viewing**: Replaced the `/streams` stub with community player streams and tournament stream cards that embed supported provider players directly and keep external fallback links.
- **Tournament detail**: Added a Live Broadcast section on tournament detail pages when a stream URL is configured, using status-based labels such as Scheduled, Live, and Replay.
- **Tests/build**: Added `tests/Feature/Stream/StreamIntegrationTest.php` for player stream publishing, admin takedown/restore, sample game trailer seeding, stream URL validation, `/streams` rendering, and tournament detail embeds. Focused stream tests pass, and `npm run build` passes. PHPStan still exits with code 1 and no diagnostics/output in this environment.

---
## ✅ Resend Transactional Mail Failover (v1.90)

- **Mail transport**: Added `resend/resend-php` and configured Laravel's `failover` mailer to try Resend first, then SMTP, then `log`.
- **Environment config**: Added `RESEND_API_KEY` to local/production env examples and Render environment provisioning; production now defaults `MAIL_MAILER` to `failover`.
- **Sender requirements**: Resend requires `MAIL_FROM_ADDRESS` to be a full email address on a verified domain, such as `noreply@mail.app-testing.website`.
- **Verification**: `php artisan test tests/Feature/Auth/EmailDeliveryTest.php` passes, and a direct `Mail::mailer('resend')` test sent successfully using the verified domain sender.

---
## ✅ Admin Rich Editor Compatibility Restore (v1.89)

- **Quill compatibility**: Restored the existing Quill 1.3 admin CDN assets after the local Quill 2 bundle broke the tournament wizard and existing rich editors.
- **CMS Body fallback**: Kept a typeable textarea fallback for Blog/News/Page Body while Quill is not ready, so the field remains editable during script initialization.
- **Existing editors**: Guarded Policy and Tournament Quill initialization without changing their editor version or markup contract.
- **Tests/build**: `npm run build` and `php artisan test tests/Feature/CMS/BlogNewsPageTest.php tests/Feature/CMS/LandingPageTest.php tests/Feature/Localization/LanguageSwitchTest.php` pass.

---
## ✅ CMS Body Rich Editor Typing Fix (v1.88)

- **CMS content editor**: Fixed the Blog/News/Page Body editor so Quill initializes reliably before event handlers read `quill.root`.
- **Livewire sync**: Scoped `wire:ignore` to the editor surface only and moved content selection/save synchronization into guarded Quill listeners.
- **Editor styling**: Added CMS-local dark Quill styling so the body field has a visible editable area even when other admin rich editor pages are not loaded.
- **Tests**: `php artisan test tests/Feature/CMS/BlogNewsPageTest.php tests/Feature/CMS/LandingPageTest.php tests/Feature/Localization/LanguageSwitchTest.php` passes.

---
## ✅ WordPress-Style Blog and News Editor (v1.87)

- **`CmsContentAdmin`**: Split Blog/News/Page authoring into a dedicated Livewire component separate from the generic CMS manager.
- **Content UI**: Replaced the modal authoring flow with a WordPress-style two-column layout: content library/list on the left, inline editor on the right.
- **Rich editor**: Added Quill rich text editing for article body content.
- **Featured image**: Replaced manual featured image path entry with Livewire image upload to public `articles` storage.
- **`CmsAdmin` cleanup**: Removed old Blog/Page modal state and save logic from the generic CMS component so it now owns only Games, Platforms, Landing, and Navigation.
- **Tests**: `php artisan test tests/Feature/CMS/BlogNewsPageTest.php tests/Feature/CMS/LandingPageTest.php` passes.

---
## ✅ CMS Section Page Split (v1.86)

- **CMS routes**: Changed CMS sidebar links from query-string tabs to real section URLs: `/admin/cms/content`, `/admin/cms/landing`, `/admin/cms/games`, `/admin/cms/platforms`, and `/admin/cms/navigation`.
- **CMS UI**: Removed the cross-CMS tab bar from section pages so each sidebar item shows only its own management surface.
- **Livewire render load**: Updated `CmsAdmin::render()` to query only the active section data instead of loading games, pages, platforms, landing sections, and navigation items on every CMS request.
- **Tests**: Updated CMS coverage to assert the content page exposes Blog & News controls without the old tab buttons. `php artisan test tests/Feature/CMS/BlogNewsPageTest.php tests/Feature/CMS/LandingPageTest.php` passes.

---
## ✅ Admin Sidebar Translation Crash Fix (v1.85)

- **Admin sidebar**: Kept grouped sidebar labels as plain strings in the nav config to avoid array-valued translation results being echoed by Blade.
- **`TranslateRenderedHtml`**: Added a safe translation fallback so rendered HTML translation keeps the original key if `__()` returns a non-scalar value.
- **Tests**: `php artisan test tests/Feature/Localization/LanguageSwitchTest.php tests/Feature/CMS/BlogNewsPageTest.php tests/Feature/CMS/LandingPageTest.php` passes.

---
## ✅ CMS Sidebar Grouping (v1.84)

- **Admin sidebar**: Grouped admin navigation into Operations, CMS, and System sections.
- **CMS section**: Moved Blog & News, Landing Page, Games, Platforms, Navigation, Policies, and Translations under one CMS sidebar section, using direct `/admin/cms?tab=...` links for existing CMS subsections.
- **Tests**: Added coverage that `/admin/cms?tab=pages` exposes Blog & News controls. `php artisan test tests/Feature/CMS/BlogNewsPageTest.php tests/Feature/CMS/LandingPageTest.php` passes.

---
## ✅ Blog and News Admin Visibility Fix (v1.83)

- **Admin navigation**: Added a dedicated Blog & News sidebar item that opens `/admin/cms?tab=pages` directly.
- **`CmsAdmin` UI**: Renamed the generic CMS Pages tab to Blog & News and added explicit Add Blog Post / Add News Article buttons while keeping generic CMS Page creation available.
- **Tests**: `php artisan test tests/Feature/CMS/BlogNewsPageTest.php tests/Feature/CMS/LandingPageTest.php` passes.

---
## ✅ CMS Blog and News Pages (v1.82)

- **`cms_pages` / `cms_page_translations`**: Added article metadata for CMS page type (`page`, `blog`, `news`), featured image path, featured flag, and localized excerpts.
- **Public routes**: Added `/blog`, `/blog/{slug}`, `/news`, and `/news/{slug}` with published-only listing/detail pages using the shared public landing shell.
- **`CmsAdmin`**: Extended the CMS Pages tab so staff can create/edit normal pages, blog posts, and news articles from the existing `/admin/cms` workflow.
- **Navigation**: Seeded Blog and News links into the editable public navigation.
- **Tests**: `php artisan test tests/Feature/CMS/BlogNewsPageTest.php tests/Feature/CMS/LandingPageTest.php` passes.

---
## ✅ Contact Inquiry Resolve UX Fix (v1.81)

- **Admin resolve action**: Resolve and Archive now validate that an inquiry is selected, update the inquiry status, reset pagination, and move the status filter to the resulting state so the admin can immediately see the updated record.
- **Admin feedback**: Added visible status badge, success/error handling, and loading/disabled states for Save Notes, Resolve, and Archive buttons.
- **Tests**: `php artisan test tests/Feature/Community/ContactInquiryTest.php` passes.
- **Build**: `npm run build` passes.
- **Known unrelated test issue**: `AdminPanelTest::test_admin_can_access_other_admin_pages` still fails when rendering User Admin without Redis available for the online indicator.

---
## ✅ Shared Guest Footer Consolidation (v1.80)

- **Guest footer architecture**: Moved the shared public footer into `components.layouts.landing` so the landing page and policy pages use the same guest footer partial as the other public pages.
- **Duplicate footer cleanup**: Removed the custom landing footer markup and manual policy footer includes to prevent guest footer drift.
- **Regression coverage**: Updated the landing page feature test to assert the shared footer Contact link renders on `/`.
- **Tests**: `php artisan test tests/Feature/CMS/LandingPageTest.php tests/Feature/CMS/PolicyPageTest.php tests/Feature/Community/ContactInquiryTest.php` passes.
- **Build**: `npm run build` passes.

---
## ✅ Landing Footer Contact Link Correction (v1.79)

- **Landing page footer**: Added Contact visibility coverage after discovering the landing page did not use the shared guest footer yet.
- **Follow-up**: Superseded by v1.80, which moved footer ownership to the shared layout instead of keeping a landing-specific footer patch.
- **Tests**: Landing page coverage asserts the Contact link renders on `/`.

---
## ✅ Contact Inquiries Public Form and Admin Inbox (v1.78)

- **`ContactPage`**: Added `/contact` support form for guests and players. Verified players get the player dashboard layout and automatic account linking; guests get the public layout.
- **`ContactInquiryAdmin`**: Added `/admin/contact-inquiries` inbox with search, status/category filters, inquiry review, internal notes, resolve, and archive actions.
- **Navigation**: Added Contact link to public footer and Support link to player sidebar/mobile More panel. Added Contact Inquiries to the admin sidebar.
- **Notifications**: New contact submissions create in-app staff notifications for SUPER_ADMIN, ADMIN, and SUPPORT_AGENT users.
- **Database**: Added `contact_inquiries` table.
- **Tests**: Added `ContactInquiryTest`; focused contact tests pass.
- **Build**: `npm run build` passes.
- **PHPStan**: Attempted on changed contact files; exited with code 1 and no diagnostics/output in this environment.

---
## ✅ Branded Lightweight Auth Email Templates (v1.77)

- **Email branding**: Replaced Laravel default verification/password-reset emails with lightweight PlayerSaloons-branded Blade templates using the platform icon, dark esports styling, direct CTA button, and fallback URL.
- **Notifications**: Added custom verification and password-reset notification classes and wired `User` to send them.
- **Environment branding**: Updated app/example env names from Laravel to PlayerSaloons so mail headers/from names no longer show Laravel.
- **Tests**: Focused auth email tests and full Auth/Identity feature tests pass.
- **PHPStan**: Not run for this Blade/notification pass.

---
## ✅ Auth Form Double-Submit Guard (v1.76)

- **Auth forms**: Login, registration, password reset, and verification resend buttons now disable during their Livewire request and show loading text, preventing repeated clicks while a submission is processing.
- **Tests**: `php artisan test tests/Feature/Auth tests/Feature/Identity` passes.
- **Build**: `npm run build` passes.

---
## ✅ Local Registration Migration and Auth Icon Refresh Fix (v1.75)

- **Database**: Ran the pending `2026_07_05_000000_add_registration_consent_fields_to_users_table` migration locally, fixing the SQLite `users.accepted_terms_at` missing-column error during registration.
- **`resources/js/app.js`**: Added a queued `refreshLucideIcons()` helper and Livewire `morph.updated` hook so Lucide icons are restored after login/register input updates, validation errors, and Livewire form morphs.
- **Tests**: `php artisan test tests/Feature/Auth tests/Feature/Identity` passes.
- **Build**: `npm run build` passes.

---
## ✅ Email Verification, Forgot Password Email, and Newsletter Deferral (v1.74)

- **Registration email verification**: New accounts now receive Laravel's email verification notification, are redirected to `/verify-email`, and cannot access verified player routes until the signed email link is opened.
- **`EmailVerification`**: Replaced the instant MVP verify button with a resend-verification-email flow.
- **Forgot password**: `/reset-password` now sends Laravel password reset link emails, and `/reset-password/{token}` handles the emailed reset form.
- **Newsletter**: Registration stores newsletter/update opt-in only. Actual newsletter sending, campaign management, audience tooling, and unsubscribe flow are deferred until the provider/workflow is selected.
- **Tests**: Added focused email-delivery coverage for verification and reset-link notifications.
- **PHPStan**: Attempted on changed auth/identity files and routes; exited with code 1 and no diagnostics/output in this environment.

---
## ✅ Registration Consent and Join Form Redesign (v1.73)

- **`Register` / `register.blade.php`**: Redesigned the Join Now form into a two-panel account setup flow. Submit is disabled until required fields are filled, passwords match, the policy checkbox is accepted, and the 18+ confirmation is checked. Added optional newsletter/platform update opt-in.
- **`users`**: Added registration consent fields for Terms, Privacy Policy, Cookie Policy, age confirmation, newsletter subscription, and policy acceptance metadata.
- **`RegisterUserAction`**: Persists consent timestamps and newsletter preference when a new player account is created.
- **Docs**: Updated `FEATURE_MAP.md` and `01_identity_onboarding.md`.
- **Tests**: Added focused registration consent tests in `RegisterUserActionTest`.
- **PHPStan**: Pending for this pass.

---
## ✅ Production Startup CMS Seeding Fix (v1.72)

- **`docker/start.sh`**: Added production startup seeding for `PlatformSeeder`, `PolicyPageSeeder`, `LandingPageSeeder`, `PublicNavigationSeeder`, and `TranslationStringSeeder` after migrations.
- **Why**: `DatabaseSeeder` included the new seeders locally, but production startup intentionally runs a curated list of seeders instead of the full demo-data `DatabaseSeeder`. Coolify therefore migrated the tables but did not populate platforms, landing sections/items, public navigation, policy footer links, or translation rows.
- **Scope**: Kept demo-heavy seeders such as `TournamentsTableSeeder` and `PlayerAccountSeeder` out of production startup. The added seeders are idempotent operational defaults.
- **Tests**: Local targeted production seed sequence passes.
- **PHPStan**: Not run for this startup script fix.

---
## ✅ Production Composer Build Fix (v1.71)

- **`Dockerfile`**: Added `mbstring`, `curl`, `dom`, and `simplexml` to the production PHP extension install list so `composer install --no-dev` satisfies the locked package platform requirements inside the Coolify build image. Added Composer build defaults for `COMPOSER_CURL_DISABLE_HTTP2=1` and `COMPOSER_PREFER_INSTALL=auto`, configured git to use HTTP/1.1, then changed the production install command to `composer install --prefer-install=auto ...`.
- **`docker-compose.prod.yml`**: Passes `COMPOSER_CURL_DISABLE_HTTP2` and `COMPOSER_PREFER_INSTALL` as build args because Coolify deploys through this compose file.
- **Why**: Coolify failed during Docker build at the Composer install step with exit code 100. The concrete Composer errors were failed dist ZIP downloads from GitHub codeload with `HTTP/2 400` for packages including `ralouphie/getallheaders` and `maennchen/zipstream-php`. Forcing source for every package then made the build do many git source syncs and fail with exit code 255, so production now uses `auto`: fast dist installs where possible and source fallback where needed.
- **Tests**: `composer validate --strict`, `composer check-platform-reqs --no-dev`, and `composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --dry-run` pass locally. Docker is not available in this workspace, so the full image build could not be reproduced here.
- **PHPStan**: Not run for this deployment Dockerfile fix.

---
## ✅ Related CMS Seed Flow (v1.70)

- **`PlatformSeeder`**: Added platform defaults for PC, Console, Mobile, and Cross-Platform so tournament/H2H platform options exist after a fresh `db:seed`.
- **`TranslationStringSeeder`**: Added main seed-flow syncing from `lang/*.json` into `translation_strings` so `/admin/translations` has database rows immediately after seeding.
- **`PolicyPageSeeder` / `LandingPageSeeder`**: Split ownership so policy rows are seeded first, then landing footer policy links are generated from active `policy_pages` instead of duplicated as an independent hardcoded footer list.
- **`public-footer.blade.php`**: Changed the shared public footer to read the active `footer` landing section/items from the database, matching the landing page footer data.
- **`TournamentsTableSeeder`**: Seeded tournaments now resolve `platform_id` from seeded platform slugs.
- **`PlayerAccountSeeder`**: Made demo player seeding idempotent so repeated `php artisan db:seed` runs do not fail on duplicate usernames/emails.
- **Docs**: Updated `ONBOARDING.md`, `FEATURE_MAP.md`, and `05_admin_operations.md` with seed dependencies and table-backed footer behavior.
- **Tests**: `php artisan db:seed` passes after repeated runs; PHP syntax checks passed for changed seeders.
- **PHPStan**: Not run for this seeding-focused pass.

---
## ✅ Admin Translation Manager and Runtime Localization (v1.69)

- **`config/localization.php` / `lang/*.json`**: Added supported locale configuration and JSON runtime files for English, French, Spanish, German, Italian, Dutch, Portuguese, Russian, Japanese, Chinese, and Polish.
- **`SetLocale` / `LanguageController`**: Added guest/session and authenticated-user language switching through `POST /language`; authenticated users persist preference in `users.locale`.
- **`TranslateRenderedHtml`**: Added rendered HTML translation for Blade and Livewire output, including visible text plus `placeholder`, `title`, `aria-label`, and `alt` attributes by exact JSON key.
- **`TranslationAdmin` / `TranslationCatalogService`**: Added `/admin/translations` for syncing JSON phrase keys into `translation_strings`, editing translations, filtering missing locale values, filling missing entries from English fallback, and exporting runtime JSON files.
- **CMS/game locale helpers**: Added current-locale helpers for game and CMS page labels with English fallback, removing hardcoded English display reads for user-visible content.
- **Docs**: Updated `ONBOARDING.md`, `FEATURE_MAP.md`, and `05_admin_operations.md` with the localization workflow and admin management rules.
- **Tests**: `php artisan test tests/Feature/Admin/TranslationAdminTest.php tests/Feature/Localization/LanguageSwitchTest.php` passes: 4 tests, 10 assertions.
- **PHPStan**: Not run for this pass.

---
## ✅ Terms and Conditions Policy Page (v1.68)

- **`PolicyPageSeeder`**: Added Terms and Conditions as a seeded policy page at `/policies/terms-and-conditions`.
- **Footer links**: Added the Terms link and allowed footer policy links to wrap on smaller screens.
- **Tests**: Updated `PolicyPageTest` to cover the Terms and Conditions public page.
- **PHPStan**: Not rerun for this small policy-content follow-up; previous run exited with code 1 and no diagnostics/output in this environment.

---
## ✅ Database-backed Policy Pages (v1.67)

- **`policy_pages`**: Added a dedicated table for legal/policy content, separate from generic `cms_pages`, with slug, title, summary, body content, active/published state, sort order, and updater tracking.
- **Public policy pages**: Added guest-accessible `/policies` index and `/policies/{slug}` detail pages for Terms and Conditions, Cookie Policy, Privacy Policy, Refund and Cancellation Policy, and Disclaimer.
- **`PolicyAdmin`**: Added `/admin/policies` for staff to edit policy title, slug, summary, rich-text body content, order, active state, and published state.
- **Seeders/navigation**: Added `PolicyPageSeeder`, wired it into `DatabaseSeeder`, and updated public footer links to real policy URLs.
- **Tests**: `php artisan test tests/Feature/CMS/PolicyPageTest.php tests/Feature/Admin/AdminPanelTest.php` passes: 25 tests, 84 assertions.
- **PHPStan**: `./vendor/bin/phpstan analyse` exited with code 1 and no diagnostics/output in this environment.

---
## ✅ Documentation Synchronization Pass (v1.66)

- **Docs sync**: Aligned `FEATURE_MAP.md`, `CARRY_FORWARD.md`, `execution_checklist.md`, `architecture_baseline.md`, and module flow docs with the current v1.65 code/documentation state.
- **Route map fixes**: Updated API wallet routes to `/api/v1/wallet/balance` and `/api/v1/wallet/transactions`, and notification read route to `{uuid}`.
- **Stale references**: Replaced old generated doc filenames with current `documentation/*.md` paths, fixed the wallet test filename, and corrected withdrawal debit wording to match the queued `CreateLedgerEntryListener` flow.
- **Tests**: Not run — documentation-only sync.
- **PHPStan**: Not run — documentation-only sync.

---
## ✅ Landing Hero Video Loop Reliability Fix (v1.65)

- **`landing-page.blade.php`**: Added `id="hero-video"` to the hero background video and changed `preload="metadata"` to `preload="auto"` to ensure the video buffers fully, which aids native loop reliability.
- **`app.js`**: Added `initHeroVideoFallback()` function to forcefully reset and replay the video when the `ended` event fires. This acts as a belt-and-suspenders guarantee for browsers (especially on mobile/iOS) that occasionally ignore the native HTML `loop` attribute when SPA navigation occurs or buffering stutters.

---
## ✅ Esports Landing Redesign, Scroll-Aware Nav & Mobile Overflow Fix (v1.64)

- **`landing-page.blade.php`**: Full esports visual redesign. Full-viewport video hero with Ken Burns scale animation, layered radial-gradient overlays, and animated cyber-grid overlay. Staggered `landing-fade-in` animations on badge, heading, body, and CTAs. Gradient CTA button with glow bloom (`landing-cta-primary`). Scroll-hint indicator below CTAs. All sections use glassmorphism cards with ambient orb accents, hover lift, and inner-border shimmer. Added a full-width gradient CTA banner section before the footer. In-page footer retained from CMS data.
- **`public-navigation.blade.php`**: Converted from `sticky` to `fixed` positioning. Starts fully transparent (`nav-transparent`) over the hero video. On scroll past 60 px transitions to solid dark blur (`nav-solid`). Mobile topbar stripped to logo + Sign In + Join Now (guests) or Dashboard shortcut (authed). All other items (nav links, profile, PWA install, logout) moved into the burger dropdown at `[data-public-mobile-menu]`. Added `overflow-x: clip` + `max-width: 100vw` guard on `#public-nav`.
- **`landing.blade.php`**: Updated to preload Orbitron + Inter via Google Fonts. Deepened background to `#050311`. Added SEO meta description.
- **`app.js` — `initPublicNav()`**: New function called from `initPublicShell()` (and on every `livewire:navigated`). Detects `.landing-hero`; if present, registers a passive scroll listener toggling `.nav-transparent` / `.nav-solid`; if absent (non-landing page), always applies `.nav-solid`. Stores handler reference on `nav._navScrollHandler` and removes it before re-binding to prevent listener accumulation across SPA navigation.
- **`app.css` — Landing CSS design system**: Added ~250 lines of landing-specific CSS prefixed `landing-`. Includes nav transparent/solid states, hero video Ken Burns keyframes, cyber-grid overlay, gradient text, scroll-arrow pulse, `landing-fade-up` animation with delay variants, `landing-section-title` / `landing-section-kicker` typography, glassmorphism card shimmer via `::before` pseudo-element, CTA button bloom via `::after`, and full mobile responsive overrides.
- **Horizontal scroll fix**: Added `html, body { overflow-x: hidden }` as a global guard. Sections with decorative negative-offset orbs use `.landing-section-overflow-clip` (`overflow-x: clip`). The `w-[600px]` decorative glow line replaced with `.landing-top-glow` (`width: min(600px, 100%)`). `.landing-games-scroll` is explicitly exempted and retains `overflow-x: auto` for the swipeable game carousel.
- **Tests**: No new tests — purely UI/CSS/JS changes. Existing `LandingPageTest` coverage remains valid for CMS data rendering.
- **PHPStan**: Not applicable — no PHP changes.

---
## ✅ Dynamic Public Navigation CMS (v1.63)

- **`public_navigation_items`**: Added table-backed public navigation items with label, URL, Lucide icon, active pattern, visibility, sort order, active state, and new-tab behavior.
- **Public navbar**: Replaced hardcoded public nav links with DB-backed items. Mobile topbar now keeps only the essential guest auth actions visible, while navigation links and install action live inside the burger menu.
- **`CmsAdmin`**: Added a Navigation tab for adding, editing, toggling, and deleting public navbar items.
- **Defaults**: Added `PublicNavigationSeeder` for Tournaments, Teams, and Dashboard defaults.
- **Tests**: Added coverage for public navigation rendering and admin management.
- **PHPStan**: `./vendor/bin/phpstan analyse` exited with code 1 and no diagnostics/output in this environment.

---
## ✅ Landing Mobile Responsiveness Pass (v1.62)

- **Landing page**: Tightened mobile hero spacing, headline sizing, CTA button tracking, section padding, carousel card widths, stat number wrapping, empty states, CTA banner spacing, and footer wrapping for phone-first usage.
- **Public navigation**: Reduced mobile topbar footprint, kept primary join action visible, and added guest Sign In / Join Now entries inside the mobile menu.
- **CSS**: Added mobile-only overrides for lower-density background patterns, hidden carousel scrollbars, and disabled hover lift transforms on touch-sized screens.
- **Tests**: Existing landing CMS tests remain the targeted coverage for landing rendering.
- **PHPStan**: `./vendor/bin/phpstan analyse` exited with code 1 and no diagnostics/output in this environment.

---
## ✅ Landing Game Carousel and Banners (v1.61)

- **Landing background**: Added a static CSS-only game-pattern treatment to the landing content background and game-card fallback banners. The pattern uses gradients only, with no animated canvas or heavy assets.
- **Games carousel**: Changed the landing games section from a fixed grid to a horizontal snap-scroll carousel.
- **`games.banner_path`**: Added a nullable banner path column for game catalog cards and surfaced it in the CMS games table/edit modal.
- **Tests**: Updated `LandingPageTest` to cover game banner rendering and admin editing of game banner/description.
- **PHPStan**: `./vendor/bin/phpstan analyse` exited with code 1 and no diagnostics/output in this environment.

---
## ✅ Dynamic Landing Page CMS (v1.60)

- **`LandingPage` / `landing-page.blade.php`**: Replaced the static homepage route with a DB-backed Livewire landing page, including `/compressed_v1.mp4` video hero, active game cards, how-it-works steps, live stats, weekly top players, feature cards, reviews, and editable footer content.
- **`landing_sections` / `landing_section_items`**: Added long-term landing content tables plus `LandingSection` and `LandingSectionItem` models.
- **`LandingPageContentService`**: Centralized landing data loading, active game lookup, live stats, and weekly top-player calculation.
- **`CmsAdmin`**: Added a Landing Page tab for editing section content and adding/editing/toggling/deleting section items.
- **Tests**: Added `LandingPageTest` for dynamic landing rendering and admin landing edits.
- **PHPStan**: `./vendor/bin/phpstan analyse` exited with code 1 and no diagnostics/output in this environment.

---
## ✅ Tournament History Detail Access Fix (v1.59)

- **`TournamentDetail`**: Player-facing tournament detail lookup now allows all non-draft tournaments instead of only active/joinable statuses. This keeps completed, cancelled, and refunded tournaments accessible from player history while preserving the draft visibility guard.
- **Tests**: Added regression coverage in `TournamentSecurityTest` confirming completed history tournament details render for players and draft tournament details still return 404.
- **PHPStan**: Not run for this documentation/test follow-up.

---
## ✅ Head-to-Head UX Enhancements & Global Active Badge (v1.58)

- **Initiate Challenge Drawer**: Removed the "Initiate Challenge" tab and replaced it with a sleek, Alpine.js-powered sliding drawer (`showInitiateDrawer`), accessible via a prominent button placed directly next to the Game Filter.
- **Highlighted Game Filter**: Redesigned the Game Filter top panel with neon gradients, glowing borders, and drop shadows to ensure it is highly noticeable and acts as the page's primary control center.
- **Global Active Duels**: Modified the `HeadToHeadList` backend logic to fetch `activeMatches` globally across all games instead of filtering by the active game. Added a glowing red notification badge to the "Active Duels" tab to instantly alert players if they have ongoing duels anywhere on the platform.
- **Bug Fix**: Removed `Cache::remember` for the `games` and `platforms` Eloquent Collections to fix a `__PHP_Incomplete_Class` Redis serialization error that occurred on some environments.

---
## ✅ Head-to-Head UI/UX Redesign & Performance Optimization (v1.57)

- **`HeadToHeadList` Component**: Optimized database queries to prevent slow load times per tab. `activeMatches`, `waitingChallenges`, and `historyMatches` are now conditionally fetched based on the active tab state rather than unconditionally queried on every render.
- **Predis Caching**: Implemented `Cache::remember` with Predis for `games` and `platforms` lookups in `HeadToHeadList::render()` to eliminate repetitive global database hits.
- **`head-to-head-list.blade.php`**: Redesigned player tabs with a polished game-menu look featuring gradients and glowing effects. Added `wire:loading` overlay with a spinner to provide immediate feedback during tab transitions and data fetching.
- **`_head-to-head-match-card.blade.php`**: Completely overhauled the match card to look like a game-style "VS" screen. Added neon accents, glassmorphism (`backdrop-blur-md`), pulsing glow on the VS badge, and more intuitive layout grouping for better user experience.
- **Testing**: Confirmed Redis/Predis connection is working.

---
## ✅ Head-to-Head Game Tabs, Guards, and Duel Prompt (v1.56)

- **`HeadToHeadList` / `head-to-head-list.blade.php`**: Split the H2H player page into `Initiate Challenge`, `Open Challenges`, `Active Duels`, and `History` tabs. Open challenges, active duels, and history are filtered by the selected game.
- **`CreateHeadToHeadChallengeAction`**: Prevents players from creating another same-game H2H challenge when they already have a waiting challenge or active duel for that game.
- **`AcceptHeadToHeadChallengeAction`**: Prevents accepting a challenge for a different selected game, accepting while the player already has a same-game waiting challenge, or accepting while the player already has a same-game active duel.
- **`HeadToHeadDuelPrompt`**: Added a dashboard-wide polling modal that alerts players when an H2H duel is active or when an open duel invite is available, with a direct link to `/head-to-head`.
- **Tests**: `php artisan test tests/Feature/Match/HeadToHeadModuleTest.php` passes: 19 tests, 71 assertions. `npm run build`, PHP syntax checks for changed PHP files, and `git diff --check` pass.
- **PHPStan**: `./vendor/bin/phpstan analyse` exited with code 1 and no diagnostics/output in this environment.

---
## ✅ Player Navigation, Upload Feedback, and PWA Landing Cache Fixes (v1.55)

- **`resources/views/livewire/profile/profile-dashboard.blade.php`**: Added immediate Alpine-side selected-file feedback and upload progress indicators for avatar and KYC document uploads so players see the upload UI as soon as a file is selected.
- **`resources/js/app.js` / `resources/css/app.css`**: Removed automatic button spinners. Livewire submit buttons are now disabled during submit, while player `wire:navigate` links use a game-style full-page loader only for uncached page transitions. Player tab links are excluded, and visited player routes are cached in `sessionStorage` to avoid repeated loader flashes.
- **`resources/views/components/layouts/dashboard.blade.php`**: Replaced the generic player skeleton overlay with a game-like page loader and route-specific skeleton shapes for wallet, profile, tournament/match, leaderboard, and default dashboard pages.
- **`public/sw.js` / `resources/js/app.js`**: Updated the service worker to stop cache-first serving HTML/navigation requests, removed `/` from the precache list, bumped the cache to `playersaloons-v3`, and added a one-time controller-update reload so stale landing-page HTML is cleared for existing users.
- **Mobile player nav**: Restored a larger mobile bottom navigation tap target, icon size, and label size after the loader work made the nav feel too small.
- **Tests**: `npm run build` passes; `php artisan test tests/Feature/Identity/ProfileDashboardTest.php tests/Feature/Identity/SubmitKycActionTest.php tests/Feature/Wallet/WalletDashboardTest.php` passes where relevant; `git diff --check` passes.
- **PHPStan**: Skipped per request for this documentation/update pass.

---
## ✅ Player Toasts, Loading States, and KYC Document Display Fix (v1.54)

- **`resources/views/components/ui/toasts.blade.php`**: Added reusable player-facing toast component for existing flash keys (`message`, `success`, `info`, `error`, `h2h_status`, `h2h_error`).
- **Player Livewire views**: Removed duplicated inline flash alert blocks from wallet, profile, team, match detail, H2H, and tournament detail player surfaces in favor of the shared toast component.
- **`resources/js/app.js` / `resources/css/app.css`**: Added automatic Livewire button preloaders for player action buttons and a skeleton overlay during `wire:navigate` dashboard transitions.
- **`dashboard.blade.php`**: Added a reusable player-page skeleton overlay inside the dashboard content pane.
- **`KycSubmission`**: Added `document_front_path` and `document_back_path` accessors backed by `document_paths`, fixing admin KYC review screens that showed “No document uploaded” even when the player upload succeeded.
- **Tests**: `php artisan test tests/Feature/Identity/SubmitKycActionTest.php tests/Feature/Identity/ProfileDashboardTest.php tests/Feature/Wallet/WalletDashboardTest.php` passes: 10 tests, 41 assertions. `npm run build` and `git diff --check` pass.
- **PHPStan**: Not run in this pass; prior environment runs exited code 1 without diagnostics.

---
## ✅ Stripe Checkout Wallet Deposits (v1.53)

- **`WalletDashboard`**: Replaced the mock deposit flow with Stripe Checkout Session creation and hosted Checkout redirect. Wallet balance is no longer credited synchronously from the UI.
- **`StripeCheckoutService`**: Added Stripe Checkout session creation with wallet/user metadata, test-mode key support via `STRIPE_SECRET_KEY`/`STRIPE_PUBLIC_KEY` or `STRIPE_SECRET`/`STRIPE_KEY`, and wallet success/cancel URLs.
- **`StripeWebhookController`**: Added signed webhook handling at `POST /stripe/webhook`; `checkout.session.completed` events with `payment_status=paid` credit wallets through `ProcessDepositAction`.
- **`ProcessDepositAction` reuse**: Stripe Checkout Session IDs are used as provider references, preserving deposit idempotency and preventing duplicate webhook double-crediting.
- **Wallet UI**: Redesigned `/wallet` with Deposit/Withdraw tabs, Stripe sandbox payment information, and a cleaner transaction ledger layout.
- **Deployment**: Added Stripe env placeholders to `.env.example` and Coolify guidance for staging webhook secrets.
- **Tests**: Added `StripeWebhookTest`; updated `WalletDashboardTest`. `php artisan test tests/Feature/Wallet/WalletDashboardTest.php tests/Feature/Wallet/StripeWebhookTest.php` passes: 4 tests, 14 assertions.
- **PHPStan**: Not yet run for this change; run `./vendor/bin/phpstan analyse` before production promotion.

---
## ✅ PWA / Reverb Console Cleanup (v1.52)

- **PWA meta tags**: Added `mobile-web-app-capable=yes` beside the existing Apple mobile web app meta tag in public, app, and dashboard layouts to satisfy current browser installability expectations.
- **`resources/js/app.js`**: Changed Laravel Echo/Reverb initialization from eager page-load setup to authenticated lazy setup. Guest/public pages no longer attempt WebSocket connections unless a `meta[name="user-uuid"]` exists and Reverb env vars are present.
- **Tests**: `npm run build` passed; `welcome` Blade render check passed.
- **PHPStan**: Not run; frontend/layout-only fix.

## ✅ Shared Public Shell + Mobile Burger Navigation (v1.51)

- **`components.layouts.partials.public-navigation`**: Added one shared public navbar for welcome and guest/public Livewire layouts. Desktop layout keeps logo left, public links centered, and auth/PWA actions right.
- **Mobile nav**: Mobile topbar keeps logo, `Sign In`, `Join Now`, and burger only. PWA install appears inside the collapsible burger menu on mobile to avoid duplicate install CTAs.
- **`components.layouts.partials.public-footer`**: Added a shared public footer so welcome and public/guest pages stay visually synchronized.
- **`components.layouts.app` / `welcome.blade.php`**: Replaced duplicated nav/footer markup with shared includes.
- **Tests**: `npm run build` passed; `welcome` Blade render check passed.
- **PHPStan**: Not run; Blade/JS layout refactor.

## ✅ PWA Install Support (v1.50)

- **PWA assets**: Added `public/manifest.json`, `public/sw.js`, and square install icons (`icon-192.png`, `icon-512.png`).
- **Service worker**: Registers app shell assets and cleans old caches on activation.
- **PWA install flow**: Added native `beforeinstallprompt` handling in `resources/js/app.js`. The install button uses the browser's direct install prompt; no tutorial/manual modal is shown.
- **Layouts**: Added manifest/theme/apple icon meta tags to public, app, and dashboard layouts.
- **Tests**: `npm run build` passed; manifest JSON and service worker syntax were validated during implementation.
- **PHPStan**: Not run; static asset/frontend change.

## 🚀 Deployment Notes

**First deployed**: 2026-06-18 via Docker Compose + Coolify on Linode (IP: 139.162.61.8)
**Domain**: app-testing.website (HTTPS — SSL active via Coolify/Let's Encrypt)

### Current Production Setup
- Docker Compose: `docker-compose.prod.yml`
- 5 containers: `app` (nginx + php-fpm), `reverb`, `worker` (Horizon), `scheduler`, `mysql`, `redis`
- `.env` loaded via Coolify environment variables UI

### Issues Encountered on First Deploy

**1. Login redirect loop (`/login?_token=...&identity=...&password=...`)**
- **Cause A**: No `trustProxies` configured — Laravel behind Coolify/Traefik didn't know it was receiving HTTPS-proxied requests, causing session/cookie inconsistency.
- **Fix A**: Added `$middleware->trustProxies(at: '*')` in `bootstrap/app.php`.
- **Cause B**: `SESSION_ENCRYPT=true` — encrypted sessions failed to decrypt on container restart/redeploy due to key loading inconsistency.
- **Fix B**: Set `SESSION_ENCRYPT=false`.
- **Cause C**: `SESSION_SECURE_COOKIE=true` but site was on HTTP — browser refused to send secure cookie over non-HTTPS.
- **Fix C**: Set `SESSION_SECURE_COOKIE=false` until SSL is configured.
- **Cause D**: Old session cookies in browser from local dev.
- **Fix D**: Clear browser cookies for the domain, or use incognito to test.

**2. `APP_URL=http://localhost`**
- Caused incorrect redirects and CSRF origin mismatches.
- Fix: Set `APP_URL=https://app-testing.website`.

### Pre-Deployment Checklist (For Future Deploys)

```
✅ bootstrap/app.php → trustProxies(at: '*') is present
✅ APP_URL = exact HTTPS domain
✅ APP_ENV = production
✅ APP_DEBUG = false
✅ SESSION_DRIVER = redis
✅ SESSION_DOMAIN = yourdomain.com
✅ SESSION_SECURE_COOKIE = true (only after SSL is active)
✅ SESSION_ENCRYPT = false
✅ REDIS_HOST = redis (container service name, not localhost)
✅ DB_HOST = mysql (container service name, not localhost)
✅ REVERB_HOST = domain name (not raw IP)
✅ REVERB_SCHEME = https, REVERB_PORT = 443 (after SSL)
✅ php artisan config:cache runs on deploy (handled in start.sh)
✅ php artisan migrate --force runs on deploy (handled in start.sh)
```

### Stripe Checkout Deposits (Coolify / Staging)

Use Stripe Dashboard webhooks for staging/production. Do not run `stripe listen` outside local development.

Required Coolify environment variables:

```env
APP_URL=https://app-testing.website
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_from_dashboard_webhook_endpoint
```

Alternative variable names are also supported:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
```

Stripe Dashboard setup:

1. Ensure the Dashboard is in test/sandbox mode while using `pk_test_` / `sk_test_` keys.
2. Create a webhook endpoint:
   `https://app-testing.website/stripe/webhook`
3. Subscribe at minimum to:
   `checkout.session.completed`
4. Copy that endpoint's signing secret (`whsec_...`) into Coolify as `STRIPE_WEBHOOK_SECRET`.
5. Redeploy or restart the app so config is rebuilt from the new env values.

### SSL Setup (Coolify — Let's Encrypt)
1. In Coolify dashboard → your application → **Domains** section
2. Enter domain: `app-testing.website`
3. Enable HTTPS/SSL toggle (Coolify + Traefik handle Let's Encrypt automatically)
4. After SSL is active, update `.env`: `SESSION_SECURE_COOKIE=true`, `REVERB_SCHEME=https`, `REVERB_PORT=443`
5. Restart (not full redeploy needed for `.env`-only changes)

### Pending Production Work
- [x] Configure SSL via Coolify (Let's Encrypt) *(done v1.30)*
- [x] Update `SESSION_SECURE_COOKIE=true`, `REVERB_SCHEME=https`, `REVERB_PORT=443` after SSL *(done v1.30)*
- [x] Verify Horizon dashboard and queue workers are processing *(confirmed active v1.29)*
- [x] `php artisan storage:link` — handled in `start.sh` on deploy
- [ ] Migrate file storage to R2/S3 — deferred, see `execution_checklist.md` → File Storage Migration
- [ ] Integrate an external payout provider — deferred during testing; manual payout processing remains active

---

## ✅ UI Harmonization & Secure KYC Storage (v1.49)
- **KYC Identity Module**: Reverted `SubmitKycAction.php` back to storing KYC IDs on the `local` private disk instead of `public` to secure sensitive identity documents from direct public access.
- **Admin Panel**: Added a secure protected route (`/admin/kyc/document/{path}`) to stream KYC files. Access requires ADMIN or KYC_REVIEWER roles. Updated `kyc-admin.blade.php` to use this secure endpoint.
- **UI/UX Fixes**:
  - Re-styled the `player-welcome-panel` in the Player Dashboard to perfectly match the sci-fi aesthetics of the Wallet Dashboard card. Replaced "Total Earnings" with "Available Balance" for exact consistency.
  - Fixed horizontal overflow on mobile for the notification bell dropdown by implementing responsive `fixed` positioning, creating a clean full-width floating panel below the header on small screens.
  - Fixed bottom spacing issue for mobile pages by increasing `padding-bottom` in `.player-main-content` to `6.5rem`, ensuring no content hides behind the new fixed bottom navbar.
- **Configuration**: Updated `APP_URL` in `.env` to `http://127.0.0.1:8000` so `Storage::url()` generates correct absolute URLs locally for avatars.
- **Tests**: 4 new tests for `KycDocumentRouteTest` to ensure role-based document streaming security.
- **PHPStan**: 0 errors at Level 5. Fixed `Route::get` redundant parameter error in `routes/web.php`.

## ✅ S3 Avatar Upload Support (v1.48)
- **Identity Module**: Added missing `league/flysystem-aws-s3-v3` package.
- Restored `UploadAvatarAction.php` to use `spatie/laravel-medialibrary` instead of local public disk.
- Enables safe persistent avatar uploads using S3-compatible endpoints (MinIO/R2) in stateless Docker/Coolify containers without relying on volume mappings.
- **Views**: Restored `$user->getFirstMediaUrl('avatar')` usage in `profile-dashboard.blade.php`.

## ✅ Mobile Layout Navigation UI Redesign (v1.47)
- **UI/UX**: Replaced the traditional mobile sidebar/burger drawer with a modern fixed-bottom navigation bar.
- Uses dynamic `env(safe-area-inset-bottom)` for mobile notch support.
- Added a slide-up "More" panel (`#mobile-more-panel`) with spring-curve animations for secondary navigation items (Wallet, Profile, Teams, Admin).
- Completely pure CSS and vanilla JS; zero server roundtrips required for toggle actions.

## ✅ Player Profile Client-Side Interaction Optimization (v1.46)

- **`profile-dashboard.blade.php`**: Converted tab switching and KYC drawer open/close from Livewire actions to Alpine state, eliminating server round trips for those interactions.
- **`ProfileDashboard`**: Added Redis-backed caching for the curated timezone list and short-lived latest KYC lookup, with database fallback if Redis is unavailable.
- **Comms Loadout**: Replaced `wire:model.live` + `wire:change` with one lightweight preference update action per toggle and skipped full re-render after persistence.
- **Tests**: `php artisan test tests/Feature/Identity/ProfileDashboardTest.php` passed.
- **PHPStan**: Not rerun for this optimization pass.

## ✅ Player Profile Tabs + Render Optimization (v1.45)

- **`ProfileDashboard`**: Added server-side tab state and replaced the full `timezone_identifiers_list()` render with a curated timezone list that also preserves the player's existing saved timezone.
- **`profile-dashboard.blade.php`**: Moved profile/account/security/comms editors behind tabs so the initial page does not render every editable section at once. Profile picture upload now lives in the Profile tab.
- **Comms Loadout**: Switched preference toggles to live model updates and verified backend persistence through `notification_preferences`.
- **Tests**: `php artisan test tests/Feature/Identity/ProfileDashboardTest.php` passed with 7 tests / 22 assertions.
- **PHPStan**: Not rerun for this optimization pass.

## ✅ Player Profile Redesign + KYC Drawer (v1.44)

- **`ProfileDashboard`**: Added database-backed account editing, profile picture upload, password change, profile email verification, and drawer state for KYC submission.
- **`profile-dashboard.blade.php`**: Rebuilt the player profile as a game-style player card with verified/not verified KYC status, hover info explaining withdrawal requirements, verified email field/button, referral copy control, profile/account/password panels, notification toggles, and KYC upload moved out of the main UI into a drawer.
- **`ProfileDashboardTest`**: Added 6 feature tests covering render behavior, profile/account persistence, email verification, password change, and KYC drawer visibility.
- **Docs**: Updated `FEATURE_MAP.md` and `01_identity_onboarding.md`.
- **Tests**: `php artisan test tests/Feature/Identity/ProfileDashboardTest.php` passed.
- **PHPStan**: `./vendor/bin/phpstan analyse` exited with code 1 and no diagnostics/output in this environment.

## ✅ H2H Timeout / Auto-Expiry Policy (v1.43)

- **`ExpireHeadToHeadMatchesJob`**: New scheduled job for conservative H2H timeout handling. Waiting challenges past `expires_at` become `EXPIRED` and refund the creator stake. Stale `IN_PROGRESS` and `WAITING_FOR_CONFIRMATION` matches move to `DISPUTED` with a system timeout note for admin review.
- **`HeadToHeadMatchStateMachine`**: Allows `IN_PROGRESS -> DISPUTED` so stale duels can escalate without auto-awarding a winner.
- **Scheduler**: Registered the H2H timeout job to run every minute in `routes/console.php`.
- **Tests**: Added coverage for expired challenge refund, stale in-progress escalation, and stale submitted-result escalation without payout. `php artisan test tests/Feature/Match/HeadToHeadModuleTest.php` passes: 14 tests, 59 assertions.
- **PHPStan**: Not rerun; previous attempts exited with code 1 and no diagnostics/output in this environment.

---

## ✅ Wallet Deposit UI Refresh Fix (v1.42)

- **`WalletDashboard`**: Reloads wallet data through a fresh relationship query during deposit/render so the updated balance and ledger feed appear immediately after a mock deposit.
- **Tests**: Added `WalletDashboardTest` covering same-response balance refresh after deposit. `php artisan test tests/Feature/Wallet/WalletDashboardTest.php` passes: 1 test, 5 assertions.
- **PHPStan**: Not rerun; previous attempts exited with code 1 and no diagnostics/output in this environment.

---

## ✅ H2H Wallet Error Handling + Backfill (v1.41)

- **`HeadToHeadList`**: Catches expected H2H domain failures from create/find/accept/cancel/result actions and displays player-readable errors instead of raw exception pages.
- **Wallet backfill migration**: Added an idempotent migration that creates active zero-balance wallets for existing users missing wallet rows. Rollback is intentionally no-op to avoid deleting financial records.
- **Local repair**: Applied the migration locally; users without wallets went from 51 to 0.
- **Tests**: Added missing-wallet and insufficient-balance `findDuel` regressions. `php artisan test tests/Feature/Match/HeadToHeadModuleTest.php` passes: 11 tests, 44 assertions.
- **PHPStan**: Not rerun; previous v1.40 PHPStan attempts exited with code 1 and no diagnostics/output in this environment.

---

## ✅ H2H Proof Upload + Admin Dispute Review (v1.40)

- **`head_to_head_matches`**: Added result proof, dispute notes/proof, disputed-by, admin resolution, resolver, and resolved-at fields.
- **`HeadToHeadList`**: Result submitters can attach proof screenshots; opponents can attach dispute notes/proof before escalating to admin review.
- **`ResolveHeadToHeadDisputeAction` / `MatchAdmin`**: Admins can review H2H disputes from `/admin/matches` and award creator, award opponent, or void/refund both stakes through ledger-backed wallet actions.
- **Tests**: Added H2H proof/dispute storage, admin winner payout, admin void/refund, and `MatchAdmin` component coverage. `php artisan test tests/Feature/Match/HeadToHeadModuleTest.php` passes: 9 tests, 40 assertions.
- **PHPStan**: `./vendor/bin/phpstan analyse` still exits with code 1 and no diagnostics/output in this environment, including raw and verbose modes. PHP syntax checks passed for changed PHP files.
- **Full suite note**: `php artisan test` currently has unrelated existing failures: Redis unavailable for online presence in `UserAdmin`, API match dispute controller calling `OpenDisputeAction` with too few arguments, `match_disputes.reason` fixture missing, and an `ADMIN` role setup issue in one KYC test.

---

## ✅ H2H Production MVP (v1.39)

- **`HeadToHeadList`**: Replaced mock in-memory H2H challenge data with DB-backed challenge queue, open challenge list, personal duel list, game handle capture, result submission, confirmation, and dispute entry point.
- **`head_to_head_challenges` / `head_to_head_matches`**: Added persisted H2H schema with game/platform, stake amount, handles, timers, statuses, result submission, and completion fields.
- **Actions/Services**: Added `CreateHeadToHeadChallengeAction`, `AcceptHeadToHeadChallengeAction`, `CancelHeadToHeadChallengeAction`, `SubmitHeadToHeadResultAction`, `ConfirmHeadToHeadResultAction`, `DisputeHeadToHeadResultAction`, stake lock/refund/payout actions, and `HeadToHeadMatchmakerService`.
- **Wallet**: Added `H2H_STAKE` and `H2H_PAYOUT` ledger types. Stakes are debited when queued/accepted; confirmed winner receives both stakes via ledger-backed payout.
- **Tests**: Added `HeadToHeadModuleTest`; H2H create/cancel/accept/confirm payout plus Livewire render flow pass. Match regression subset passes: 29 tests, 92 assertions.
- **PHPStan**: Attempted on H2H PHP files, but the runner exited with code 1 without diagnostics/output in this environment. PHP syntax checks passed for changed H2H PHP files.

---

## ✅ Welcome Logo Text Cleanup (v1.38)

- **`welcome.blade.php`**: Removed the visible `PLAYERSALOONS` wordmark text beside the header logo while keeping the logo image, page title, and image alt text intact.
- **Tests**: Not run; Blade-only visual cleanup.
- **PHPStan**: Not run; no PHP code changed.

---

## ✅ Match Confirmation Flow Alignment (v1.37)

- **`MatchStateMachine`**: Updated the canonical result flow to `IN_PROGRESS -> WAITING_FOR_CONFIRMATION -> COMPLETED/DISPUTED`. `RESULT_SUBMITTED` remains transition-compatible only for legacy rows.
- **`MatchAdmin` / `AdminDashboard`**: Aligned admin override and active-match counting with `WAITING_FOR_CONFIRMATION`.
- **Tests**: Updated match lifecycle tests to match auto-start behavior and the `ResolveDisputeAction` `User` actor contract. `MatchModuleTest`, `ConfirmResultFlowTest`, and `MatchStateMachineTest` pass: 24 tests, 75 assertions.
- **PHPStan**: Attempted at Level 5, but the runner exited with code 1 without diagnostics/output in this environment. PHP syntax checks passed for changed PHP files.

---

## ✅ Player Notification Bell (v1.36)

- **`NotificationBell`**: New Livewire component for the player dashboard topbar. Loads the latest 10 authenticated-user notifications, shows unread count/state, and supports single or bulk mark-as-read actions scoped through `auth()->user()->notifications()`.
- **Realtime frontend**: Added Laravel Echo + Pusher JS client wiring in `resources/js/app.js` for Laravel Reverb private user channels (`user.{uuid}`). Incoming `.notification.received` broadcasts dispatch a Livewire refresh event.
- **`dashboard.blade.php`**: Replaced static mock notification dropdown with the database-backed `<livewire:notification-bell />` component and exposes the authenticated user's UUID as a meta tag for Echo channel subscription.
- **Tests**: 6 tests in `NotificationBellTest`, all passing.
- **PHPStan**: 0 errors on changed PHP files at Level 5.

---

## ✅ Broadcast Notification Admin Panel (v1.35)

- **`BroadcastNotificationAdmin`**: New Livewire admin component at `/admin/notifications`. CRUD for `broadcast_messages` — create, edit, expire (all admins), delete permanently (SUPER_ADMIN only). Search by title/message, paginated list with status badges (Active/Scheduled/Expired).
- **Blade**: Modular partials — `_table.blade.php`, `_form-modal.blade.php`, `_confirm-modal.blade.php` — all reusable via `@include`.
- **`tests/TestCase.php`**: Added `withoutMiddleware(UpdateUserOnlineStatus::class)` globally to prevent Redis connection errors across all test HTTP requests.
- **`BroadcastMessage` model**: Added `@property` PHPDoc annotations + fixed `$fillable` to `list<string>`.
- **Tests**: 9 new tests in `BroadcastNotificationAdminTest` — access guards, create, edit, expire, delete (SUPER_ADMIN), search. All passing.
- **PHPStan**: 0 new errors at Level 5.

---

## ✅ Fix: missing `last_login_at` migration (v1.34)

- **`2026_06_19_112307_add_last_login_at_to_users_table.php`**: Added missing migration for `last_login_at` column on `users` table. Column was being updated in `Login.php` (since v1.29) but no migration existed — caused `SQLSTATE HY000: no such column` on local SQLite.
- **Tests**: No new tests — covered by existing login flow.
- **PHPStan**: N/A.

---

## ✅ Online presence tracking — Redis-based (v1.33)

- **`UpdateUserOnlineStatus`**: New middleware appended to `web` group. Sets `user_online:{id}` Redis key with 300s TTL on every authenticated request.
- **`User::isOnline()`**: New method — returns `true` if Redis key exists for the user.
- **`user-admin.blade.php`**: Added online dot indicator (emerald = online, slate = offline) next to username in the user list table.
- **Tests**: 4 new tests in `OnlinePresenceTest` — middleware sets key for auth user, skips guest, `isOnline()` true/false. All passing.
- **PHPStan**: 0 new errors at Level 5.

---

## ✅ Security tests — Tournament access controls (v1.32)

- **`TournamentSecurityTest`**: 3 new security-adjacent tests covering: Join button restricted to PLAYER role, player tournament listing filters out DRAFT/CANCELLED/COMPLETED statuses, and `viewRestrictedDetails` policy hides Matches/Players/Activity tabs from non-participants.
- **Tests**: 3 new tests, all passing.
- **PHPStan**: 0 errors at Level 5.

---

## ✅ NotifyAdminsOfKycSubmissionListener (v1.31)

- **`NotifyAdminsOfKycSubmissionListener`**: New queued listener on `notifications` queue. Handles `UserKycSubmitted` — sends an in-app notification to all users with `ADMIN` or `SUPER_ADMIN` role via `NotificationService`.
- **`EventServiceProvider`**: Registered `UserKycSubmitted → NotifyAdminsOfKycSubmissionListener`.
- **Tests**: 3 new tests in `NotifyAdminsOfKycSubmissionListenerTest` — admins notified, non-admins skipped, all admin roles covered. All passing.
- **PHPStan**: 0 errors on new files at Level 5.

---

## ✅ SSL env vars updated in Coolify (v1.30)

- **Deployment**: SSL now active on `app-testing.website`. Updated Coolify environment variables: `SESSION_SECURE_COOKIE=true`, `REVERB_SCHEME=https`, `REVERB_PORT=443`. Restarted via Coolify (no redeploy needed).
- **Tests**: No code changes — config-only update.
- **PHPStan**: N/A.

---

## ✅ Quick Fixes & Production Verification (v1.29)

### `last_login_at` — now updated on login
- **`app/Livewire/Auth/Login.php`**: Added `$user?->update(['last_login_at' => now()])` after successful `Auth::attempt()`.
- **`app/Modules/Identity/Models/User.php`**: Added `last_login_at` to `$fillable` and `casts` (`'datetime'`). Column already existed in the migration.
- **Usage**: Currently recorded but not yet displayed in UI. Planned use: Admin User panel (last seen), future online presence tracking.

### Horizon Production Verified
- `/horizon` accessible at production URL. Status: **Active**. Workers confirmed running via `worker` container.
- Zero metrics are expected until real user activity generates jobs.

### Documentation Reorganization
- Deleted redundant files: `PlayerSaloons_New_Admin_Features_Implementation_Plan_v1.md`, `PlayerSaloons_Baseline_Addendum_v1.md`, all `Zone.Identifier` artifacts.
- Moved all docs to `documentation/` folder (git mv — history preserved).
- Created `documentation/ONBOARDING.md` — single start-here file with setup, conventions, doc update format, data storage map, Definition of Done, and feature/bug tracking guide.
- Created `documentation/guides/r2-storage-migration.md` — step-by-step R2 migration guide (Obsidian-compatible).
- Updated `README.md` to point to ONBOARDING.md.
- Fixed `.gitignore` — removed incorrect ignores of `/documentation` folder and root doc files.
