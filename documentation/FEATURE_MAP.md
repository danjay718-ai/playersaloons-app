# PlayerSaloons — Feature Map

**Last Updated**: 2026-08-18 (v1.115)

Quick-reference for developers. Maps every feature to its route, Livewire component, backend actions, and test coverage.

For architecture decisions and rationale, see `architecture_baseline.md`.
For step-by-step user flows and file-level details, see `/documentation/`.

---

## 🏗️ Core Architecture Principles
- **Modular Domain Design**: Business logic under `app/Modules/` by domain.
- **State Machine Driven**: All lifecycle transitions go through `AbstractStateMachine` classes.
- **Ledger-Based Finance**: Immutable `ledger_entries` are the source of truth for all balances.
- **Event-Driven**: Cross-module communication via thin domain events (no Eloquent models in events).
- **Security**: Granular RBAC via Spatie, UUIDs for external IDs, Four-Eyes checks for financial approvals.
- **Actions are self-authorizing**: Role/policy guards live inside Actions, not just at the component level.

---

## 🗺️ Route & Component Map

### Public Routes (unauthenticated)

| Route | Component | Description |
|---|---|---|
| `GET /` | `app/Livewire/Landing/LandingPage.php` | Dynamic landing page backed by editable landing sections/items, horizontal active-game carousel with optional `games.banner_path` banners, live stats, and weekly top players |
| `GET /tournaments` | `app/Livewire/Tournament/PublicTournamentList.php` | Public tournament listing |
| `GET /blog` | `app/Livewire/CMS/BlogIndex.php` | Public Blog listing for published CMS blog posts |
| `GET /blog/{slug}` | `app/Livewire/CMS/BlogArticleView.php` | Public Blog detail page for one published CMS blog post |
| `GET /news` | `app/Livewire/CMS/NewsIndex.php` | Public News listing for published CMS news articles |
| `GET /news/{slug}` | `app/Livewire/CMS/NewsArticleView.php` | Public News detail page for one published CMS news article |
| `GET /about` | `app/Livewire/AboutPage.php` | Public About Us page backed by CMS-managed system settings |
| `GET /policies` | `app/Livewire/Policies/PolicyIndex.php` | Public legal/policy index backed by `policy_pages` |
| `GET /policies/{slug}` | `app/Livewire/Policies/PolicyPageView.php` | Public legal/policy detail page for active, published policy pages |
| `GET /contact` | `app/Livewire/Community/ContactPage.php` | Public/player contact support form; guests use public layout and verified players use the player dashboard layout |
| `GET /newsletter/unsubscribe/{user}` | signed route closure | Removes a recipient from future newsletter campaigns |
| `GET /promotions/{advertisement}/click` | route closure | Tracks a currently active promotion click and redirects to its destination |
| `GET /login` | `app/Livewire/Auth/Login.php` | Login (guest only) |
| `GET /register` | `app/Livewire/Auth/Register.php` | Registration (guest only) with required policy acceptance, required 18+ confirmation, and optional newsletter/update opt-in |
| `GET /reset-password` | `app/Livewire/Auth/PasswordReset.php` | Sends password reset email link (guest only) |
| `GET /reset-password/{token}` | `app/Livewire/Auth/PasswordReset.php` | Password reset form from emailed token (guest only) |
| `POST /language` | `app/Http/Controllers/LanguageController.php` | Switches the active UI locale for guests via session and authenticated users via `users.locale` |
| `POST /stripe/webhook` | `app/Http/Controllers/StripeWebhookController.php` | Stripe webhook receiver for sandbox/staging deposit fulfillment |

### Player Routes (auth required)

| Route | Component | Description |
|---|---|---|
| `GET /dashboard` | `app/Livewire/Dashboard/PlayerDashboard.php` | Cockpit overview: balance, recent matches, upcoming tournaments |
| `GET /my-tournaments` | `app/Livewire/Tournament/MyTournamentsList.php` | Player's active + history tournaments with stats banner |
| `GET /tournaments/browse` | `app/Livewire/Tournament/PlayerTournamentList.php` | Browse & filter all active tournaments |
| `GET /tournaments/{uuid}/view` | `app/Livewire/Tournament/TournamentDetail.php` | Tournament detail, registration, check-in, bracket, matches, and embedded tournament broadcasts when stream URLs are configured |
| `GET /matches/{uuid}` | `app/Livewire/Match/MatchDetail.php` | Match lobby: result submission, evidence, dispute |
| `GET /head-to-head` | `app/Livewire/Match/HeadToHeadList.php` | Preserved player-wager flow; registered only when `PLAYER_WAGER_ENABLED=true` (disabled by default) |
| `GET /leaderboards` | `app/Livewire/Match/LeaderboardList.php` | Leaderboard (stub) |
| `GET /streams` | `app/Livewire/Stream/StreamList.php` | Player stream hub where players publish normalized `stream_channels` for YouTube, Twitch, or Facebook and watch other player/tournament embeds |
| `GET /chat` | `app/Livewire/Community/GlobalChat.php` + `app/Http/Controllers/Community/ChatController.php` | Reverb-backed comms hub for persisted global chat, player-to-player direct chat, team chat with join/switch warning, unread badges, avatars, player search, profile stats modal, follow, and message actions. Global chat retains the latest 100 messages |
| `GET /wallet` | `app/Livewire/Wallet/WalletDashboard.php` | Wallet balance, transparent dynamic-fee Stripe deposits, withdrawal requests, and transaction history |
| `GET /profile` | `app/Livewire/Profile/ProfileDashboard.php` | Game-style player profile with avatar, account/profile/password updates, email verification, KYC, notification preferences, and authenticator-based 2FA setup/disable controls |
| `GET /reviews` | `app/Livewire/Community/PlayerReviewPage.php` | Submit or edit one moderated 1–5 star platform review |
| `GET /two-factor-challenge` | `app/Livewire/Auth/TwoFactorChallenge.php` | Completes a pending password login with TOTP or a single-use recovery code |
| `GET /teams` | `app/Livewire/Team/TeamDashboard.php` | Team management: create, invite, roster, captaincy |
| `GET /verify-email` | `app/Livewire/Auth/EmailVerification.php` | Email verification notice + resend verification email |
| `GET /email/verify/{id}/{hash}` | signed route closure | Signed email verification link; marks email verified and unlocks verified player routes |
| `POST /logout` | inline route closure | Invalidates session and redirects to `/` |

### Admin Routes (auth + ADMIN/SUPER_ADMIN role)

| Route | Component | Description |
|---|---|---|
| `GET /admin` | `app/Livewire/Admin/AdminDashboard.php` | Stats grid + recent KYC/withdrawal activity |
| `GET /admin/profile` | `app/Livewire/Admin/AdminProfile.php` | Staff profile |
| `GET /admin/tournaments` | `app/Livewire/Admin/TournamentAdmin.php` | Tournament list + lifecycle state transitions |
| `GET /admin/tournaments/create` | `app/Livewire/Admin/TournamentForm.php` | 4-step creation wizard |
| `GET /admin/tournaments/{id}/edit` | `app/Livewire/Admin/TournamentForm.php` | Edit existing tournament |
| `GET /admin/tournaments/{id}/matches` | `app/Livewire/Admin/TournamentMatches.php` | Tournament-specific fixtures view with status filtering and player/team identity |
| `GET /admin/matches` | `app/Livewire/Admin/MatchAdmin.php` | Dispute queue + Match monitoring |
| `GET /admin/streams` | `app/Livewire/Stream/StreamList.php` | Stream moderation surface for viewing player streams, taking down invalid/abusive streams, and restoring streams |
| `GET /admin/kyc` | `app/Livewire/Admin/KycAdmin.php` | Review KYC submissions (approve/reject) |
| `GET /admin/kyc/document/{path}` | inline route closure | Secure file stream for viewing private KYC ID images |
| `GET /admin/withdrawals` | `app/Livewire/Admin/WithdrawalAdmin.php` | Review withdrawals + Four-eyes approval process |
| `GET /admin/users` | `app/Livewire/Admin/UserAdmin.php` | User list: suspend, roles, wallet view |
| `GET /admin/compliance` | `app/Livewire/Admin/ComplianceAdmin.php` | Apply, search, inspect, expire, and revoke auditable player compliance blocks |
| `GET /admin/audit-logs` | `app/Livewire/Admin/AuditLogAdmin.php` | Spatie activity log viewer with filters |
| `GET /admin/cms/{section?}` | `app/Livewire/Admin/CmsAdmin.php` | CMS section pages for Landing Page (`landing`), Games (`games`), Platforms (`platforms`), Navigation (`navigation`), and About Us (`about`); renders only the active section data |
| `GET /admin/translations` | `app/Livewire/Admin/TranslationAdmin.php` | Translation manager for UI phrase keys; imports `lang/*.json`, edits `translation_strings`, fills missing values, and exports JSON runtime files |
| `GET /admin/policies` | `app/Livewire/Admin/PolicyAdmin.php` | Dedicated policy editor for Terms and Conditions, Cookie Policy, Privacy Policy, Refund and Cancellation Policy, and Disclaimer |
| `GET /admin/notifications` | `app/Livewire/Admin/BroadcastNotificationAdmin.php` | Broadcast messages: create, edit, expire, delete (SUPER_ADMIN) |
| `GET /admin/contact-inquiries` | `app/Livewire/Admin/ContactInquiryAdmin.php` | Contact inquiry inbox: status counters, search/filter, category/status badges, email reply shortcut, internal notes, resolve, archive |
| `GET /admin/newsletters` | `app/Livewire/Admin/NewsletterAdmin.php` | Subscriber audience search, campaign sending, delivery totals, and recent campaign history |
| `GET /admin/system-settings` | `app/Livewire/Admin/SystemSettingsAdmin.php` | Admin-adjustable referral rewards, tournament auto-forfeit timeout, H2H commission, and localization visibility toggles |
| `GET /admin/roles-permissions` | `app/Livewire/Admin/RolePermissionAdmin.php` | Grouped role-permission toggles; SUPER_ADMIN permissions are protected from modification |
| `GET /admin/geo-blocking` | `app/Livewire/Admin/BlockedCountriesAdmin.php` | Admin management of blocked IP regions and custom messages |
| `GET /admin/advertisements` | `app/Livewire/Admin/AdvertisementAdmin.php` | Scheduled advertisement/promotion CRUD and click totals |
| `GET /admin/player-reviews` | `app/Livewire/Admin/PlayerReviewAdmin.php` | Approve or reject player star reviews before public display |
| `GET /admin/staff-activity` | `app/Livewire/Admin/StaffActivityDashboard.php` | Per-staff action breakdown (ADMIN/SUPER_ADMIN) |

### REST API Routes (`/api/v1`)

| Endpoint | Controller | Description |
|---|---|---|
| `GET /api/v1/tournaments` | `TournamentApiController` | Paginated list with filters |
| `GET /api/v1/tournaments/{uuid}` | `TournamentApiController` | Tournament detail |
| `POST /api/v1/tournaments/{uuid}/register` | `TournamentApiController` | Register for tournament |
| `POST /api/v1/tournaments/{uuid}/checkin` | `TournamentApiController` | Check in to tournament |
| `GET /api/v1/matches/{uuid}` | `MatchApiController` | Match detail |
| `POST /api/v1/matches/{uuid}/result` | `MatchApiController` | Submit match result |
| `POST /api/v1/matches/{uuid}/dispute` | `MatchApiController` | Open dispute |
| `GET /api/v1/wallet/balance` | `WalletApiController` | Wallet balance |
| `GET /api/v1/wallet/transactions` | `WalletApiController` | Transaction history (paginated) |
| `POST /api/v1/wallet/withdraw` | `WalletApiController` | Request withdrawal (KYC required) |
| `GET /api/v1/profile` | `ProfileApiController` | View profile |
| `PUT /api/v1/profile` | `ProfileApiController` | Update profile |
| `POST /api/v1/teams` | `TeamApiController` | Create team |
| `GET /api/v1/teams/{uuid}` | `TeamApiController` | Team detail |
| `POST /api/v1/teams/{uuid}/invite` | `TeamApiController` | Invite player |
| `GET /api/v1/notifications` | `NotificationApiController` | Notification list (paginated) |
| `POST /api/v1/notifications/{uuid}/read` | `NotificationApiController` | Mark as read |

### Shared Player Layout Components

| Surface | Component | Description |
|---|---|---|
| Player dashboard topbar | `app/Livewire/Notification/NotificationBell.php` | Shows latest 10 user notifications, unread count, single/all mark-as-read actions, and refreshes from realtime Reverb broadcasts |
| Player toast notifications | `resources/views/components/ui/toasts.blade.php` | Shared toast surface for player-facing `session()->flash()` feedback (`message`, `success`, `info`, `error`, `h2h_status`, `h2h_error`) |
| Legacy H2H duel prompt | `app/Livewire/Match/HeadToHeadDuelPrompt.php` | Feature-gated preserved prompt; global polling has been removed |
| Player dashboard shell | `resources/views/components/layouts/dashboard.blade.php` | Desktop player shell with a viewport-fixed hover-expand sidebar (`#desktop-sidebar`) and a desktop content offset matching the collapsed sidebar width; mobile uses the fixed bottom navigation and More panel |
| Player loading states | `resources/js/app.js`, `resources/css/app.css`, `resources/views/components/layouts/dashboard.blade.php` | Disables Livewire submit buttons during submit and shows a game-style full-page loader for uncached player `wire:navigate` route changes; tab links are excluded and visited routes are cached in `sessionStorage` |
| Player upload feedback | `resources/views/livewire/profile/profile-dashboard.blade.php` | Shows immediate selected-file feedback and Livewire upload progress for avatar and KYC document uploads |
| Language switcher | `resources/views/components/localization/language-switcher.blade.php` | Reusable locale dropdown shown in guest/public, player, and admin shells; posts to `/language` and reads supported languages from `config/localization.php` |
| Auth emails | `app/Notifications/Auth/*`, `resources/views/emails/auth/*`, `config/mail.php` | Lightweight PlayerSaloons-branded verification and password reset emails with direct CTA and fallback URL; delivered through Laravel `failover` using Resend, SMTP, then `log` |
| Contact support | `app/Livewire/Community/ContactPage.php` | Guest/player support form linked from public footer and player navigation; stores account-linked inquiries when authenticated |
| Realtime comms hub | `app/Livewire/Community/GlobalChat.php`, `ChatController`, `ChatService`, `ChatMessageSent`, `PlayerFollow` | Alpine-driven `/chat` UI with JSON message endpoints, private Reverb channels for global/direct/team conversations, unread state from `chat_participants.last_read_at`, avatars, player profile stats, follow, and message entry points; message persistence succeeds even if the realtime broadcaster is temporarily unavailable |

### Shared Public Layout Components

| Surface | Component/File | Description |
|---|---|---|
| Public navbar | `resources/views/components/layouts/partials/public-navigation.blade.php` | Fixed-position navbar backed by `public_navigation_items`. Transparent over the hero video, transitions to solid dark background on scroll (`initPublicNav()` in `app.js`, `.nav-transparent` / `.nav-solid` CSS classes). Desktop shows nav links; mobile topbar shows only logo + auth actions (Sign In / Join Now or Dashboard shortcut); all other items move into the burger dropdown. |
| Public footer | `resources/views/components/layouts/partials/public-footer.blade.php` | Shared public footer for welcome and public/guest Livewire pages. Reads the active `footer` landing section/items from the database; seeded footer policy links are generated from active `policy_pages`. |
| Landing shell | `resources/views/components/layouts/landing.blade.php` | Full-bleed landing shell for the dynamic homepage. Preloads Orbitron + Inter from Google Fonts. Uses fixed (not sticky) nav so the hero video is visible beneath it on load. |
| Landing page | `resources/views/livewire/landing/landing-page.blade.php` | Esports-themed dynamic landing: full-viewport video hero with `id="hero-video"` for JS replay fallback, CMS-editable sections, horizontal snap-scroll game carousel (`.landing-games-scroll`), glassmorphism cards, animated fade-in content, gradient CTA banner, and managed footer. |
| Landing CSS design system | `resources/css/app.css` (`.landing-*` classes) | All landing styles are prefixed `landing-`. Key classes: `.landing-page-root` (outer overflow clip), `.landing-hero`, `.landing-main-pattern`, `.landing-section-overflow-clip` (sections with decorative orbs), `.landing-games-scroll` (the only permitted horizontal scroll), `.landing-gradient-text`, `.landing-section-title`, `.landing-section-kicker`, `.landing-card`, `.landing-stat-card`, `.landing-cta-primary`, `.landing-fade-in` (+ delay variants), `.landing-top-glow`. |
| Scroll-aware nav JS | `resources/js/app.js` — `initPublicNav()` | Detects `.landing-hero` presence. If found: registers a passive scroll listener and toggles `.nav-transparent` / `.nav-solid` on `#public-nav` at a 60 px threshold. If not found (non-landing pages): always applies `.nav-solid`. Cleans up previous scroll listeners on Livewire SPA navigation to avoid memory leaks. |
| Public shell behavior | `resources/js/app.js` | Handles public mobile burger menu, scroll-aware nav, hero video replay fallback, native PWA install prompt, service worker registration, and lazy authenticated Echo setup. |
| PWA manifest/service worker | `public/manifest.json`, `public/sw.js`, `public/icon-192.png`, `public/icon-512.png` | Installable app metadata, square PWA icons, static asset caching, and network-only HTML navigation so stale landing pages are not served after logout. |
| Horizontal scroll containment | `html, body { overflow-x: hidden }` in `app.css` | Global guard. Decorative sections use `overflow-x: clip`. The only intentional horizontal scroll is `.landing-games-scroll`. |

### Localization Runtime

| Surface | File/Component | Description |
|---|---|---|
| Locale selection | `app/Http/Middleware/SetLocale.php` | Sets `app()->getLocale()` from authenticated `users.locale`, guest session locale, or app fallback. Safely handles requests before a session store is attached. |
| Rendered UI text translation | `app/Http/Middleware/TranslateRenderedHtml.php` | Translates rendered HTML text nodes plus `placeholder`, `title`, `aria-label`, and `alt` attributes by exact JSON key. Also handles Livewire JSON payloads that contain rendered HTML. |
| Geo-Blocking interception | `app/Http/Middleware/BlockRestrictedCountries.php` | Checks user IP against `blocked_countries` table and blocks non-admin access with a 403 error. |
| Supported language list | `config/localization.php` | Defines supported locales: English, French, Spanish, German, Italian, Dutch, Portuguese, Russian, Japanese, Chinese, and Polish. |
| Runtime files | `lang/*.json` | Laravel JSON translation files used at runtime. All ten non-English catalogs match the 82-key English catalog as of v1.114; admin edits are exported here from `translation_strings`. |
| Admin editing source | `translation_strings` table | Database-backed phrase catalog edited from `/admin/translations`; JSON files are export/cache output. |

---

## 📦 Feature → Backend Mapping

### Identity & Onboarding
| Feature | Action/Service | Event | Listener |
|---|---|---|---|
| Register | `RegisterUserAction` | `UserRegistered` | `CreateWalletListener` |
| Referral rewards | `RegisterUserAction`, `AwardReferralRewardsAction` | `WalletCredited` (`DEPOSIT`) | `QualifyReferralOnDepositListener` |
| Online presence | `UpdateUserOnlineStatus` (middleware) | — | — |
| KYC Submit | `SubmitKycAction` | `UserKycSubmitted` | `NotifyAdminsOfKycSubmissionListener` |
| KYC Approve | `ApproveKycAction` | `UserKycApproved` | — |
| KYC Reject | `RejectKycAction` | `UserKycRejected` | — |
| Suspend User | `SuspendUserAction` | `UserSuspended` | — |
| Update Profile | `UpdateProfileAction` | — | — |
| Upload Avatar | `UploadAvatarAction` | — | — |
| Verify Email from Profile | `ProfileDashboard::verifyEmail()` | `EmailVerified` | — |
| Enable/disable 2FA | `EnableTwoFactorAction`, `DisableTwoFactorAction`, `TotpService` | — | Login completion handled by `TwoFactorChallenge` |
| Compliance blocking | `ApplyComplianceBlockAction`, `RevokeComplianceBlockAction` | — | `EnsureNotComplianceBlocked` enforces active records on verified player routes |
| KYC admin document compatibility | `KycSubmission::document_front_path`, `KycSubmission::document_back_path` accessors | — | — |

### CMS & Public Landing
| Feature | Action/Service | Event | Listener |
|---|---|---|---|
| Dynamic landing page content | `LandingPageContentService` | — | — |
| Landing section/card editing | `CmsAdmin::saveLandingSection()`, `CmsAdmin::saveLandingItem()` | — | — |
| Landing defaults | `LandingPageSeeder` | — | Creates standard landing sections/items and derives footer policy links from seeded active `policy_pages` |
| Public navigation defaults | `PublicNavigationSeeder` | — | — |
| Policy page defaults | `PolicyPageSeeder` | — | — |
| Platform defaults | `PlatformSeeder` | — | Seeds PC, Console, Mobile, and Cross-Platform platform rows used by tournament/H2H forms and demo tournaments |
| Active games on landing | `Game` + `GameTranslation` query, optional `games.banner_path` | — | — |
| Game trailer seed embeds | `GameTrailerStreamSeeder` + `StreamChannel` | — | Seeds sample YouTube trailer channels for the five default games; rendered under `/streams` → Game Trailers |
| Live landing stats | `GameMatch`, `HeadToHeadMatch`, `LedgerEntry`, `User`, `Game` aggregate queries | — | — |
| Public policy rendering | `PolicyPage` + `PolicyIndex` / `PolicyPageView` | — | — |
| Policy editing | `PolicyAdmin::savePolicy()` | — | — |
| Blog/news rendering | `CmsPage` + `BlogIndex` / `BlogArticleView` / `NewsIndex` / `NewsArticleView` | — | Filters to published posts by CMS page type |
| Blog/news authoring | `CmsContentAdmin::saveContent()` | — | WordPress-style inline editor for type, uploaded featured image, featured flag, localized title/excerpt/content |
| UI translation management | `TranslationAdmin` + `TranslationCatalogService` | — | `TranslationStringSeeder` and admin actions sync `lang/*.json` into `translation_strings`, fill missing values from English fallback, and export JSON runtime files |
| About Us content | `CmsAdmin::saveAbout()` + `SystemSetting` | — | Stores editable `about.title`, `about.subtitle`, and `about.body` values rendered by `AboutPage` |
| Player/tournament stream embeds | `StreamList`, `TournamentForm`, `StreamEmbedService`, `StreamChannel` | — | Player streams, tournament broadcasts, and game trailers are normalized in `stream_channels`; create/update/delete writes are activity-logged while reads are not |
| Provider live status | `ProviderLiveStatusService` | — | `RefreshProviderLiveStatusesJob` polls configured YouTube/Twitch/Facebook APIs every two minutes and preserves manual state on unavailable/error responses |

### Tournament Lifecycle
| Feature | Action/Service | Event | Listener/Job |
|---|---|---|---|
| Register for tournament | `RegisterForTournamentAction` | `TournamentSeatReserved` | `TournamentNotificationListener` |
| Check-in | `CheckinParticipantAction` | `PlayerCheckedIn` | — |
| Close registration | `CloseRegistrationAction` | `TournamentRegistrationClosed` | — |
| Generate bracket | `BracketGenerationService` | `TournamentBracketGenerated` | — |
| Start tournament | `StartTournamentAction` | `TournamentStarted` | `AutoStartMatchesListener`, `BroadcastTournamentLifecycleListener` |
| Reconcile competition lifecycle | `TournamentLifecycleReconciler` | Lifecycle events | `tournaments:reconcile-lifecycle` every minute; catches up overdue states and routes opted-in underfill cancellation through the refund flow |
| Generate recurring competitions | `GenerateRecurringTournamentAction` | — | `tournaments:auto-generate` every five minutes; timezone-aware, row-locked, capped, and idempotent via unique template/start occurrence |
| Complete tournament | — | `TournamentCompleted` | `AwardPrizesListener` |
| Cancel + refund | `CancelTournamentAction` + `ProcessRefundAction` | `TournamentCancelled` | `IssueRefundsListener` |

### Match Execution
| Feature | Action/Service | Event | Listener/Job |
|---|---|---|---|
| Submit result | `SubmitMatchResultAction` | `MatchResultSubmitted` | `NotifyParticipantsListener` |
| Confirm result | `ConfirmMatchResultAction` | `MatchCompleted` | `AdvanceWinnerListener`, `BroadcastBracketUpdateListener` |
| Auto-forfeit | `ForfeitMatchAction` | `MatchForfeited` | — |
| Open dispute | `OpenDisputeAction` + `SubmitEvidenceAction` | `MatchDisputed` | — |
| Resolve dispute | `ResolveDisputeAction` | `MatchCompleted` | `AdvanceWinnerListener` |
| Auto-start | — | — | `AutoStartMatchesListener` (on `TournamentStarted` + `MatchCompleted`) |
| Auto-forfeit timeout | — | — | `AutoForfeitJob` (scheduler, every minute) |

### Legacy Player-Wager Head-to-Head Duels
This domain remains implemented for future activation, but its player surfaces and expiry scheduler are disabled unless `PLAYER_WAGER_ENABLED=true`. Current platform-created H2H uses the normal tournament lifecycle with an enforced 1v1 shape.
Per-game `head_to_head_ratings` use a 1200 baseline and K-factor 32. `HeadToHeadRatingService` updates confirmed or adjudicated results once, stores before/after snapshots on the match, and `HeadToHeadMatchmakerService` prioritizes nearby ratings with a widening wait-time window.
| Feature | Action/Service | Event | Listener/Job |
|---|---|---|---|
| Create H2H challenge | `CreateHeadToHeadChallengeAction` + `LockHeadToHeadStakeAction` | — | Prevents another same-game waiting challenge or active duel before locking stake |
| Matchmake / accept challenge | `HeadToHeadMatchmakerService` + `AcceptHeadToHeadChallengeAction` | — | Accept requires the selected game to match and blocks another same-game waiting challenge or active duel |
| Cancel waiting challenge | `CancelHeadToHeadChallengeAction` + `RefundHeadToHeadStakeAction` | — | — |
| Submit H2H result | `SubmitHeadToHeadResultAction` | — | Optional proof upload stored on `head_to_head_matches.result_proof_path` |
| Confirm H2H result | `ConfirmHeadToHeadResultAction` + `ResolveHeadToHeadStakeAction` | — | Winner receives the combined stake pool less the configured `h2h.commission_percentage` (10% default) |
| Dispute H2H result | `DisputeHeadToHeadResultAction` | — | Optional dispute proof/notes stored for admin review |
| Resolve H2H dispute | `ResolveHeadToHeadDisputeAction` | — | Admin can award creator, award opponent, or void/refund both stakes from `/admin/matches` |
| H2H timeout/expiry | — | — | `ExpireHeadToHeadMatchesJob` every minute; expired waiting challenges refund, stale active/submitted matches escalate to admin review |

### Wallet & Finance
| Feature | Action/Service | Event | Listener |
|---|---|---|---|
| Stripe deposit checkout | `StripeCheckoutService` | — | — |
| Deposit fulfillment | `StripeWebhookController` → `ProcessDepositAction` | `WalletCredited` | `SendDepositNotificationListener` |
| Request withdrawal | `RequestWithdrawalAction` | `WithdrawalRequested` | — |
| Approve withdrawal | `ApproveWithdrawalAction` | `WithdrawalApproved` | `CreateLedgerEntryListener` (debit) |
| Process withdrawal | `ProcessWithdrawalAction` | — | — |
| Prize award | `AwardPrizesListener` | `PrizeAwarded` | `TournamentNotificationListener` |
| Entry fee | `WalletService::debit()` (inside RegisterForTournamentAction) | `EntryFeeCollected` | — |
| H2H settlement | `ResolveHeadToHeadStakeAction` | — | Credits an idempotent net payout after deducting `h2h.commission_percentage` from the combined stake pool |

### Team Management
| Feature | Action/Service | Event | Job |
|---|---|---|---|
| Create team | `CreateTeamAction` | `TeamCreated` | — |
| Invite member | `InviteToTeamAction` | `TeamMemberInvited` | `ExpireTeamInvitationsJob` |
| Accept invite | `AcceptTeamInvitationAction` | `TeamMemberJoined` | — |
| Decline invite | `DeclineTeamInvitationAction` | — | — |
| Revoke invite | `RevokeTeamInvitationAction` | — | — |
| Remove member | `RemoveTeamMemberAction` | `TeamMemberRemoved` | — |
| Transfer captain | `TransferTeamCaptainAction` | `TeamCaptainChanged` | — |
| Disband team | `DisbandTeamAction` | `TeamDeleted` | — |

### Notifications & Realtime
| Feature | Component/Service | Event | Listener/Frontend |
|---|---|---|---|
| Player notification bell | `NotificationBell` + `NotificationService` | `BroadcastNotification` (`user.{uuid}`) | Laravel Echo/Reverb listener dispatches `notification.received` to refresh Livewire |
| Contact inquiry staff alert | `ContactPage` + `NotificationService` | — | Creates in-app notifications for SUPER_ADMIN, ADMIN, and SUPPORT_AGENT users |
| Chat message broadcast | `ChatController::send()` + `ChatService::sendMessage()` | `ChatMessageSent` | Private Reverb channel `chat.{conversationUuid}` authorized by conversation participation or active team membership |

---

## 🧪 Test Coverage Map

### Feature Tests (`tests/Feature/`)
| Test File | What It Covers |
|---|---|
| `Identity/RegisterUserActionTest.php` | Registration success, wallet creation, event dispatch, validation failures |
| `Identity/SubmitKycActionTest.php` | KYC submission, admin-facing document path compatibility, resubmission from rejected, event dispatch |
| `Identity/ProfileDashboardTest.php` | Player profile render, profile/account updates, email verification, password change, KYC drawer visibility, comms preference persistence |
| `Identity/OnlinePresenceTest.php` | Middleware sets Redis key for auth user, skips guest, `isOnline()` true/false |
| `Identity/NotifyAdminsOfKycSubmissionListenerTest.php` | Admin notification on KYC submit, non-admins not notified, all admin roles notified |
| `Authorization/PolicyTest.php` | All 8 policies across Tournament, Match, Wallet, Withdrawal, KYC, Team, User, Dispute |
| `Api/ApiEndpointsTest.php` | 401/403 gates, pagination, status filters, referral URL format |
| `Community/NotificationServiceTest.php` | Preference-aware delivery (in-app, realtime, email) |
| `Community/NotificationBellTest.php` | Player notification bell list, unread count, single/all mark-as-read, ownership guard, realtime refresh event |
| `Community/ChatIntegrationTest.php` | Persisted global chat, direct player chat participant isolation, team chat membership guard, player-safe search/profile/follow, Reverb broadcast dispatch, and send resilience when broadcasting is unavailable |
| `Admin/AdminPanelTest.php` | Admin access guards, KYC approve/reject, match override, tournament create (TournamentForm), staff activity |
| `Admin/BroadcastNotificationAdminTest.php` | Access guards, create/edit/expire/delete broadcasts, SUPER_ADMIN delete restriction, search filter |
| `Admin/TranslationAdminTest.php` | Translation manager access, JSON key sync into `translation_strings`, missing-locale filtering |
| `Localization/LanguageSwitchTest.php` | Guest session locale rendering and authenticated user locale persistence |
| `CMS/PolicyPageTest.php` | Public policy index/detail rendering, inactive/unpublished 404s, admin policy editing, player admin guard |
| `Wallet/WalletServiceTest.php` | Deposit idempotency, withdrawal lifecycle, ledger sum = cached balance, listener idempotency |
| `Wallet/WalletDashboardTest.php` | Wallet page Stripe Checkout redirect for deposits |
| `Wallet/StripeWebhookTest.php` | Signed Stripe webhook deposit crediting, idempotency, invalid signature rejection |
| `Tournament/TournamentModuleTest.php` | Registration, check-in, bracket generation, cancellation, refunds, prize distribution |
| `Tournament/TournamentSecurityTest.php` | Join button role restriction, listing status filter, viewRestrictedDetails policy |
| `Match/MatchModuleTest.php` | Result submission, confirmation flow, dispute, forfeit, bracket advancement |
| `Match/HeadToHeadModuleTest.php` | H2H challenge queue, stake lock/refund/payout, proof upload, admin winner ruling, admin void/refund, timeout escalation, MatchAdmin component resolution |
| `Match/ConfirmResultFlowTest.php` | Full flow: confirmResult → MatchCompleted → AdvanceWinnerListener + AutoForfeitJob timeout |
| `Team/TeamModuleTest.php` | All 11 team actions: create, invite, accept, decline, revoke, remove, transfer, disband |

### Unit Tests (`tests/Unit/`)
| Test File | What It Covers |
|---|---|
| `StateMachines/TournamentStateMachineTest.php` | All transitions including rollbacks, guards |
| `StateMachines/MatchStateMachineTest.php` | All match state paths including dispute/forfeit |
| `StateMachines/WalletStateMachineTest.php` | ACTIVE/SUSPENDED/FROZEN transitions + SUPER_ADMIN guard |
| `StateMachines/WithdrawalStateMachineTest.php` | Full approval pipeline, four-eyes guard, null-safety |
| `StateMachines/KycStateMachineTest.php` | NOT_SUBMITTED → SUBMITTED → APPROVED/REJECTED |
| `StateMachines/InvitationStateMachineTest.php` | PENDING → ACCEPTED/DECLINED/EXPIRED/REVOKED |
| `StateMachines/SeatReservationStateMachineTest.php` | RESERVED → CONFIRMED/EXPIRED/CANCELLED |
| `Tournament/BracketGenerationServiceTest.php` | 2, 5, 6, 8-player bracket sizes with bye math |

### Pending Tests (Not Yet Written)
See `execution_checklist.md` → Testing Debt section for the full list.

---

## 🛠️ Technical Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11+ |
| Frontend | Livewire 3 + Alpine.js |
| Realtime | Laravel Reverb (WebSockets) |
| CSS | Tailwind CSS v4 |
| Icons | Lucide Icons |
| Auth | Laravel session auth + Sanctum (API) |
| RBAC | Spatie Laravel Permission |
| Audit Logging | Spatie Laravel Activity Log |
| Queue/Jobs | Laravel Horizon (Redis) |
| Payments | Stripe Checkout + Stripe webhooks for wallet deposits |
| Database | MySQL 8 (production and local dev); SQLite in-memory for isolated tests |
| Cache/Session | Redis |
| File Storage | Local `public` disk (dev/staging) → R2/S3 (production, pending) |
| Testing | PHPUnit (Feature + Unit) |
| Static Analysis | PHPStan Level 5–8 (Larastan) |
| Deployment | Docker Compose + Coolify (Linode) |
