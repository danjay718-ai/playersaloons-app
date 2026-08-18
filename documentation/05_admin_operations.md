# User Flow: Admin & Operations

This document details the operational workflows for staff members (ADMIN / SUPER_ADMIN).

## 1. KYC Review
Approving or rejecting player identity submissions.

*   **Action**: Admin reviews submitted documents and updates the status.
*   **UI Component**: `app/Livewire/Admin/KycAdmin.php`
*   **View**: `resources/views/livewire/admin/kyc-admin.blade.php`
*   **Logic (Actions)**:
    *   `app/Modules/Identity/Actions/ApproveKycAction.php`: Marks KYC as approved. Enforces `KYC_REVIEWER | ADMIN | SUPER_ADMIN` role.
    *   `app/Modules/Identity/Actions/RejectKycAction.php`: Marks KYC as rejected with a reason. Same role guard.
*   **Security**: `KycAdmin` calls `$reviewer->can('approve'/'reject', $submission)` (via `KycPolicy`) before executing actions.
*   **Connected Files**:
    *   `app/Modules/Identity/StateMachines/KycStateMachine.php`: Governs the review lifecycle.
    *   `app/Modules/Identity/Policies/KycPolicy.php`: Restricts review actions to staff.
    *   `app/Modules/Identity/Events/UserKycApproved.php`.

## 2. Withdrawal Approval
Verifying and processing cash-out requests.

*   **Action**: Admin reviews a pending withdrawal and approves it for payout.
*   **UI Component**: `app/Livewire/Admin/WithdrawalAdmin.php`
*   **Logic (Actions)**:
    *   `app/Modules/Wallet/Actions/ApproveWithdrawalAction.php`: Moves request to APPROVED status. Enforces `FINANCE_OPERATOR | ADMIN | SUPER_ADMIN` role and four-eyes check.
    *   `app/Modules/Wallet/Actions/ProcessWithdrawalAction.php`: Confirms the external payout was sent and marks the withdrawal as PROCESSED. Same role guard.
    *   `app/Modules/Wallet/Listeners/CreateLedgerEntryListener.php`: Debits the wallet asynchronously when `WithdrawalApproved` is dispatched.
*   **Security**: `WithdrawalAdmin::approve()` and `processPayout()` both call `$reviewer->can('approve', $withdrawal)` (via `WithdrawalPolicy`) before executing actions.
*   **Connected Files**:
    *   `app/Modules/Wallet/Policies/WithdrawalPolicy.php`: Enforces the **Four-Eyes Principle** (reviewer cannot be the requester).
    *   `app/Modules/Wallet/StateMachines/WithdrawalStateMachine.php`: Manages the multi-step approval flow.
    *   `app/Modules/Wallet/Events/WithdrawalApproved.php`.

## 3. Tournament Control
Managing the lifecycle of tournaments.

*   **Action**: Admin publishes, cancels, or manually progresses a tournament.
*   **UI Component**: `app/Livewire/Admin/TournamentAdmin.php` (list + lifecycle transitions)
*   **Create/Edit Component**: `app/Livewire/Admin/TournamentForm.php` (multi-step creation form at `/admin/tournaments/create`)
*   **Fixtures Component**: `app/Livewire/Admin/TournamentMatches.php` at `/admin/tournaments/{id}/matches`, with match-status filtering and individual/team identity rendering.
*   **Logic (Actions)**:
    *   `app/Modules/Tournament/Actions/CreateTournamentAction.php`: Initializes a new tournament.
    *   `app/Modules/Tournament/Actions/CancelTournamentAction.php`: Triggers the cancellation and refund flow.
    *   `app/Console/Commands/AutoCancelTournaments.php`: Runs every minute and sends opted-in underfilled tournaments through the normal cancellation/refund action after their start time.
    *   `app/Console/Commands/AutoGenerateRecurringTournaments.php`: Runs hourly and creates the next missing daily, weekly, or monthly occurrence from recurring templates.
*   **Connected Files**:
    *   `app/Modules/Tournament/StateMachines/TournamentStateMachine.php`: The core engine for tournament states.
    *   `app/Modules/Tournament/Actions/ProcessRefundAction.php`: Automates refunds for cancelled tournaments.
    *   `app/Modules/Tournament/Jobs/AutoCancelTournamentJob.php`: Triggered if participation is too low.

## 4. Match & Dispute Management
Resolving conflicts and overriding results.

*   **Action**: Admin reviews dispute evidence and makes a final ruling.
*   **UI Component**: `app/Livewire/Admin/MatchAdmin.php`
*   **Logic (Actions)**:
    *   `app/Modules/Match/Actions/ResolveDisputeAction.php`: Finalizes a dispute with a chosen outcome (e.g., PLAYER_A_WINS). Accepts `User $actor` and enforces `ADMIN | SUPER_ADMIN` role check.
    *   `app/Modules/Match/Actions/ResolveHeadToHeadDisputeAction.php`: Resolves H2H disputes by awarding creator, awarding opponent, or voiding/refunding both locked stakes.
    *   `app/Modules/Match/Actions/ForfeitMatchAction.php`: Manually forfeits a match for a player.
*   **Connected Files**:
    *   `app/Modules/Match/Models/MatchDispute.php`: Tracks dispute status.
    *   `app/Modules/Match/Models/HeadToHeadMatch.php`: Stores H2H result proof, dispute notes/proof, and admin resolution metadata.
    *   `app/Modules/Match/Policies/MatchPolicy.php`: Grants admins override permissions.
    *   `app/Modules/Match/Listeners/AdvanceWinnerListener.php`: Re-triggered after dispute resolution to progress the bracket.

## 5. User Moderation
Managing player accounts and roles.

*   **Action**: Admin suspends a user or updates their roles; authorized staff can manage grouped role permissions from `/admin/roles-permissions`.
*   **UI Component**: `app/Livewire/Admin/UserAdmin.php`
*   **Roles & Permissions Component**: `app/Livewire/Admin/RolePermissionAdmin.php`; permission toggles are grouped by name prefix and SUPER_ADMIN permissions cannot be modified.
*   **Logic (Actions)**:
    *   `app/Modules/Identity/Actions/SuspendUserAction.php`: Disables account access. Enforces `ADMIN | SUPER_ADMIN`.
    *   `app/Modules/Identity/Actions/AssignRoleAction.php`: Updates RBAC roles. **Restricted to `SUPER_ADMIN` only** (not plain ADMIN).
*   **Online Presence**: Each row in the user table shows a dot indicator — emerald if online (active in last 5 min via `User::isOnline()` / Redis), slate if offline.
*   **Connected Files**:
    *   `app/Modules/Identity/Policies/UserPolicy.php`: Restricts moderation to staff.
    *   `app/Modules/Identity/Events/UserSuspended.php`.
    *   `app/Http/Middleware/UpdateUserOnlineStatus.php`: Sets `user_online:{id}` Redis key (TTL 300s) on every authenticated web request.

## 6. Broadcast Notifications
Managing platform-wide broadcast messages.

*   **Route**: `/admin/notifications`
*   **UI Component**: `app/Livewire/Admin/BroadcastNotificationAdmin.php`
*   **Blade Partials** (reusable via `@include`):
    *   `livewire/admin/partials/broadcast/_table.blade.php`
    *   `livewire/admin/partials/broadcast/_form-modal.blade.php`
    *   `livewire/admin/partials/broadcast/_confirm-modal.blade.php`
*   **Actions by role**:
    *   ADMIN / SUPER_ADMIN: Create, Edit, Expire (sets `ends_at = now()`)
    *   SUPER_ADMIN only: Delete (permanent)
*   **Status logic**: Active (within window), Scheduled (starts_at in future), Expired (ends_at in past).

## 7. Staff Activity Dashboard
Monitoring admin actions across the platform.

*   **Action**: Super-admin views a per-staff breakdown of all logged actions for a date range.
*   **Route**: `/admin/staff-activity`
*   **UI Component**: `app/Livewire/Admin/StaffActivityDashboard.php`
*   **View**: `resources/views/livewire/admin/staff-activity-dashboard.blade.php`
*   **Access**: `ADMIN` and `SUPER_ADMIN` only (additional role check in `boot()`).
*   **Features**:
    *   Filter by staff username and date range (defaults to last 7 days).
    *   Per-staff action count breakdown (e.g., "kyc_approved ×5, withdrawal_approved ×2").
    *   Top-10 actions summary across all staff in the period.

## 8. CMS & Landing Page Management
Managing public content, Blog/News articles, game catalog labels, and the editable landing page.

*   **Route**: `/admin/cms/{section?}`
*   **UI Components**:
    *   `app/Livewire/Admin/CmsContentAdmin.php`: Blog, News, and static page authoring.
    *   `app/Livewire/Admin/CmsAdmin.php`: Landing, Games, Platforms, and Navigation management.
*   **Admin Sidebar Group**: CMS section contains Blog & News, Landing Page, Games, Platforms, Navigation, About Us, Policies, and Translations.
*   **Admin Section Routes**:
    *   `/admin/cms/content`: Blog, News, and generic CMS page authoring with a WordPress-style editor.
    *   `/admin/cms/landing`: Landing page sections and landing section items.
    *   `/admin/cms/games`: Game catalog labels, descriptions, landing banners, and active status.
    *   `/admin/cms/platforms`: Platform rows and active status.
    *   `/admin/cms/navigation`: Public navigation items.
    *   `/admin/cms/about`: About Us title, subtitle, and rich HTML body stored as system settings.
*   **Render Scope**: `CmsAdmin::render()` only loads data for the active section route to avoid rendering all CMS management surfaces on every request.
*   **Public Blog/News Routes**:
    *   `/blog`: Published CMS blog post listing.
    *   `/blog/{slug}`: Published CMS blog post detail.
    *   `/news`: Published CMS news article listing.
    *   `/news/{slug}`: Published CMS news article detail.
*   **Public About Route**: `/about`, rendered by `app/Livewire/AboutPage.php` from `about.title`, `about.subtitle`, and `about.body` settings.
*   **CMS Page Tables**:
    *   `cms_pages`: Stores slug, content type (`page`, `blog`, `news`), featured image path, featured flag, published timestamp, creator, and soft-delete state.
    *   `cms_page_translations`: Stores localized title, excerpt, and HTML content.
*   **Navigation Tables**:
    *   `public_navigation_items`: Stores editable public navbar links, visibility rules, icons, active match patterns, sort order, and status.
*   **Landing Tables**:
    *   `landing_sections`: Stores section-level content such as hero title/body/video, section headings, CTA, sort order, and visibility.
    *   `landing_section_items`: Stores cards/steps/stat labels/features/reviews/footer links for each section.
*   **Landing Public Component**: `app/Livewire/Landing/LandingPage.php`
*   **Data Service**: `app/Modules/CMS/Services/LandingPageContentService.php`
*   **Editable Landing Areas**:
    *   Hero copy, CTA, and video path (`/compressed_v1.mp4` by default).
    *   How-it-works steps.
    *   Stat card labels/icons/order; values are computed live, not manually entered.
    *   Feature cards.
    *   Review cards.
    *   Footer copy and links.
*   **Game Catalog Landing Fields**:
    *   `games.banner_path`: Optional image path used by the landing game carousel.
    *   `game_translations.description`: Editable game description shown on landing game cards.
*   **Blog/News Workflow**:
    *   Staff create or edit entries from `/admin/cms/content`.
    *   Content type controls whether a published record appears under `/blog` or `/news`.
    *   Draft records (`published_at = null`) and records of the wrong type are hidden from public article routes.
    *   Featured records sort ahead of regular posts in public listings.
    *   The editor uses Quill rich text and stores formatted HTML.
    *   Featured images are uploaded through Livewire to the public `articles` storage path; admins no longer paste image paths manually.
*   **Public Navigation Areas**:
    *   Desktop nav links come from active `public_navigation_items`.
    *   Mobile burger menu shows the same nav items plus the install action; guest Sign In / Join Now remain visible in the mobile topbar.
    *   Visibility supports everyone, guests only, signed-in users, players, staff, and guests-or-players.
*   **Database-driven Areas**:
    *   Games list comes from active rows in `games` and `game_translations`, displayed as a horizontal carousel.
    *   Live stats are computed from matches, H2H matches, prize/H2H payout ledger entries, active users, and active games.
    *   Top players of the week are computed from completed tournament matches and weekly prize activity.
*   **Defaults**:
    *   `database/seeders/LandingPageSeeder.php` creates the standard landing sections/items for fresh installs.
    *   Footer policy links are not duplicated as a static list; `LandingPageSeeder` reads active `policy_pages` seeded by `PolicyPageSeeder` and generates the footer items from those policy rows.

## 9. Policy Pages
Managing public legal/policy pages outside the generic CMS page system.

*   **Route**: `/admin/policies`
*   **UI Component**: `app/Livewire/Admin/PolicyAdmin.php`
*   **Public Routes**:
    *   `/policies`: Index of active, published policies.
    *   `/policies/{slug}`: Detail view for one active, published policy.
*   **Policy Table**:
    *   `policy_pages`: Stores slug, title, summary, content, sort order, active state, published timestamp, and last updater.
*   **Editor**: Body content uses the same Quill rich text editor pattern as the tournament wizard, storing formatted HTML for public rendering.
*   **Seeded Policy Pages**:
    *   Terms and Conditions (`/policies/terms-and-conditions`)
    *   Cookie Policy (`/policies/cookie-policy`)
    *   Privacy Policy (`/policies/privacy-policy`)
    *   Refund and Cancellation Policy (`/policies/refund-and-cancellation-policy`)
    *   Disclaimer (`/policies/disclaimer`)
*   **Reason for Separate Area**: Policy pages are operational/legal content and are intentionally separate from `cms_pages`, so they can have fixed expected slugs, dedicated admin UX, and predictable public footer links.
*   **Seed Relationship**: `PolicyPageSeeder` owns the policy records. `LandingPageSeeder` depends on those seeded policy records to create the landing/public footer links, so `DatabaseSeeder` runs policies before landing content.

## 10. Translation Management
Managing user-facing UI phrases and locale JSON runtime files.

*   **Route**: `/admin/translations`
*   **UI Component**: `app/Livewire/Admin/TranslationAdmin.php`
*   **View**: `resources/views/livewire/admin/translation-admin.blade.php`
*   **Data Model**: `app/Modules/Localization/Models/TranslationString.php`
*   **Service**: `app/Modules/Localization/Services/TranslationCatalogService.php`
*   **Tables / Files**:
    *   `translation_strings`: Database editing source for each phrase key and locale.
    *   `lang/*.json`: Runtime translation files exported from the database.
    *   `users.locale`: Stores the preferred locale for authenticated users.
*   **Admin Workflow**:
    *   Click **Sync JSON** after developers add new `lang/en.json` keys in code.
    *   Use search, locale filter, and **Missing only** to find untranslated phrases.
    *   Click a row edit action to fill translations for all supported locales.
    *   Click **Save & Export** so the database changes are written back to `lang/*.json`.
    *   Click **Fill Missing** when missing entries should be populated with English fallback text first; real translations can still be edited later.
*   **Runtime Flow**:
    *   `SetLocale` chooses locale from `users.locale`, session, or fallback.
    *   `TranslateRenderedHtml` translates rendered Blade/Livewire text and supported attributes by exact JSON key.
    *   Game/CMS translation helpers read current locale first and fall back to English.
*   **Defaults**: `TranslationStringSeeder` runs in the main `DatabaseSeeder` flow and syncs current `lang/*.json` phrase keys into `translation_strings`.
*   **Current catalog state (v1.114)**: German, Spanish, French, Italian, Japanese, Dutch, Polish, Portuguese, Russian, and Chinese each contain the same 82 keys as English, with localized values replacing the earlier fallback-heavy catalogs.
*   **Tests**:
    *   `tests/Feature/Admin/TranslationAdminTest.php`
    *   `tests/Feature/Localization/LanguageSwitchTest.php`

## 11. Contact Inquiries
Managing support/contact messages submitted by guests and signed-in players.

*   **Public Route**: `/contact`
*   **Admin Route**: `/admin/contact-inquiries`
*   **Public/Player UI Component**: `app/Livewire/Community/ContactPage.php`
*   **Admin UI Component**: `app/Livewire/Admin/ContactInquiryAdmin.php`
*   **Data Model**: `app/Modules/Community/Models/ContactInquiry.php`
*   **Inbox Summary**: New, in-review, resolved, and archived counters double as status filters; the admin dashboard separately highlights new and in-review inquiries requiring attention.
*   **Reply Workflow**: The detail panel uses distinct category/status badges and provides a prefilled `mailto:` shortcut while internal notes remain private to staff.
*   **Features**:
    *   Guests and players can submit name, email, category, subject, and message.
    *   Signed-in verified players see the form inside the player dashboard layout and their account is linked automatically.
    *   Public footer links to `/contact`; player sidebar and mobile More panel include Support.
    *   Staff can search/filter by status/category, review details, add internal notes, mark resolved, or archive.
    *   New inquiries create in-app staff notifications for SUPER_ADMIN, ADMIN, and SUPPORT_AGENT users.
*   **Statuses**: `new`, `in_review`, `resolved`, `archived`.
*   **Tests**: `tests/Feature/Community/ContactInquiryTest.php`

## 12. Newsletter Management
Managing the opted-in newsletter audience and sending auditable email campaigns.

*   **Admin Route**: `/admin/newsletters`
*   **Authorization**: ADMIN and SUPER_ADMIN only.
*   **Unsubscribe Route**: signed `/newsletter/unsubscribe/{user}` link included in every campaign email.
*   **Admin UI Component**: `app/Livewire/Admin/NewsletterAdmin.php`
*   **Campaign Model**: `app/Modules/Community/Models/NewsletterCampaign.php`
*   **Audience Rules**: Only active, email-verified users with `newsletter_subscribed = true` are included at send time.
*   **Campaign Records**: Store creator, subject/content, recipient count, successful/failed deliveries, status, and send timestamp.
*   **Failure Handling**: A failed recipient does not stop the remaining campaign; the failure is counted and logged.
*   **Tests**: `tests/Feature/Community/NewsletterManagementTest.php`

## 13. Stream Moderation
Managing player-created stream embeds and tournament broadcast URLs.

*   **Player Route**: `/streams`
*   **Admin Route**: `/admin/streams`
*   **Shared UI Component**: `app/Livewire/Stream/StreamList.php`
*   **Data Model**: `app/Modules/Stream/Models/StreamChannel.php`
*   **Embed Service**: `app/Modules/Stream/Support/StreamEmbedService.php`
*   **Features**:
    *   Players can publish their own YouTube, Twitch, or Facebook Live stream URL from `/streams`.
    *   Admins can view player streams from `/admin/streams`, take down invalid/abusive streams with a reason, and restore streams.
    *   Tournament admins can add YouTube, Twitch, and Facebook broadcast URLs in the tournament wizard; these render on `/streams` and tournament detail pages.
    *   The five default seeded games have sample YouTube trailer channels from `GameTrailerStreamSeeder`, rendered under `/streams` → Game Trailers.
    *   Streams are normalized in `stream_channels` using nullable ownership columns: `user_id`, `tournament_id`, or `game_id`.
    *   Stream create/update/delete writes are activity-logged by the model; admin takedown/restore actions add explicit activity entries. Viewing streams is not logged.
    *   Provider status is refreshed every two minutes by `RefreshProviderLiveStatusesJob`. Missing credentials or provider errors retain the manual `is_live` value and persist `provider_status`, `provider_checked_at`, and `provider_status_error` for diagnosis.
*   **Tests**: `tests/Feature/Stream/StreamIntegrationTest.php`

## 14. Compliance & Blacklisting
Managing auditable player access restrictions independently from account suspension.

*   **Admin Route**: `/admin/compliance`
*   **UI Component**: `app/Livewire/Admin/ComplianceAdmin.php`
*   **Model**: `app/Modules/Identity/Models/ComplianceBlock.php`
*   **Actions**: `ApplyComplianceBlockAction`, `RevokeComplianceBlockAction`
*   **Enforcement**: `EnsureNotComplianceBlocked` middleware protects verified player routes. Active records return HTTP 403; expired or revoked records do not block access.
*   **Rules**:
    *   Only ADMIN and SUPER_ADMIN can apply or revoke a block.
    *   Administrator accounts cannot be blacklisted through the action.
    *   A player can have only one active block at a time.
    *   Categories are fraud, chargeback, platform abuse, identity risk, and legal restriction.
    *   Every apply/revoke operation writes an activity-log record with the responsible administrator and reason.
*   **Tests**: `tests/Feature/Identity/ComplianceBlockTest.php`

## 15. H2H Rating Operations
Head-to-head results maintain a game-specific ELO rating used by automatic matchmaking.

*   **Model**: `app/Modules/Match/Models/HeadToHeadRating.php`
*   **Service**: `app/Modules/Match/Services/HeadToHeadRatingService.php`
*   **Rules**: New game ratings start at 1200 with K-factor 32. Confirmed and admin-adjudicated wins update ratings once; refunds do not affect ratings.
*   **Auditability**: Each resolved match stores creator/opponent ratings before and after processing plus `rating_processed_at` for idempotency.
*   **Matchmaking**: The initial acceptable difference is 100 rating points and expands by 25 points per waiting minute up to 400.

## 16. System Settings

*   **Admin Route**: `/admin/system-settings`
*   **Authorization**: ADMIN and SUPER_ADMIN only.
*   **Referral Settings**: Enable/disable rewards and adjust the referrer and new-player amounts. Values are read when the referred player’s first successful deposit qualifies the referral, and updates record `updated_by`.
*   **H2H Commission**: Configure `h2h.commission_percentage` (10% default). `ResolveHeadToHeadStakeAction` deducts this percentage from the combined two-player stake pool before creating the winner's idempotent payout ledger entry.
*   **Localization**: Toggle the visibility of the Language Switcher on Guest and Admin pages (defaults to hidden). Players always see the switcher in the dashboard.

## 17. Advertisements & Promotions

*   **Admin Route**: `/admin/advertisements` for ADMIN/SUPER_ADMIN.
*   **Player Placement**: Latest active scheduled promotion appears as a dismissible banner across authenticated player pages.
*   **Controls**: Title, description, optional external image/destination URL, CTA label, activation, and start/end schedule.
*   **Tracking**: Active promotion clicks are counted before redirecting to the configured destination.

## 18. Player Review Moderation

*   **Player Route**: `/reviews`; one editable 1–5 star review per player.
*   **Admin Route**: `/admin/player-reviews` for ADMIN/SUPER_ADMIN approval or rejection.
*   **Moderation Rule**: New submissions and edits are pending; only approved reviews render publicly on the landing page.

## 19. Geo-Blocking (Compliance)

*   **Admin Route**: `/admin/geo-blocking` for ADMIN/SUPER_ADMIN.
*   **Middleware**: `BlockRestrictedCountries` running on global routes (except `/admin*` and system internals).
*   **Functionality**: Resolves player IPs via `stevebauman/location`. If the country code is in the blocked list, access is denied with a 403 screen displaying the admin's custom block message for that country. Admin UI manages the blocked countries list and instantly resets the cached active blocklist upon change.

## 🧪 Isolated Test Cases
### 1. Security & Guards
*   **Role Protection**: `test_non_admin_cannot_access_admin_dashboard` / `test_player_cannot_access_staff_activity_dashboard`
    *   Assert redirect to login or 403.
*   **Audit Logging**: `test_admin_action_is_logged_in_activity_log`
    *   Perform an approval/rejection.
    *   Assert `activity_log` has entry with `causer_id` as the admin.

### 2. Operational Workflows
*   **KYC Approval**: `test_kyc_admin_can_approve_kyc`
    *   Assert `kyc_submissions.status` updates to `APPROVED`.
*   **Match Override**: `test_match_admin_can_override_result`
    *   Manually set winner for an ongoing match.
    *   Assert `status = COMPLETED` and `winner_registration_id` set.
*   **Tournament Create**: `test_tournament_admin_can_create_tournament`
    *   Uses `TournamentForm` component (not `TournamentAdmin`).
    *   Assert `tournaments` row with `status = DRAFT`.
*   **Staff Activity**: `test_admin_can_access_staff_activity_dashboard`, `test_staff_activity_dashboard_shows_staff_members`, `test_staff_activity_dashboard_filters_by_date`, `test_staff_activity_dashboard_filters_by_staff_name`
*   **Landing CMS**: `test_admin_can_update_landing_section_and_create_item`
    *   Assert admin can update section content and add landing cards/items.
*   **Navigation CMS**: `test_admin_can_manage_public_navigation_items`
    *   Assert admin can create and toggle public navbar items.
*   **Blog/News CMS**: `CMS/BlogNewsPageTest`
    *   Assert admin can create a blog post with excerpt/featured metadata and public Blog/News routes show only published records of the correct type.
*   **Dynamic Landing Render**: `test_landing_page_renders_seeded_content_video_and_games`
    *   Assert `/` renders seeded content, the video path, game catalog cards, and dynamic sections.
*   **Policy Pages**: `CMS/PolicyPageTest`
    *   Assert guests can view seeded policy pages, inactive/unpublished policies 404, admins can edit policy content, and players cannot access `/admin/policies`.
*   **Translation Manager**: `Admin/TranslationAdminTest`
    *   Assert admins can open `/admin/translations`, sync JSON keys into `translation_strings`, and filter missing locale rows.
*   **Language Switching**: `Localization/LanguageSwitchTest`
    *   Assert guest session locale changes rendered HTML and authenticated user locale preference persists.

### 📋 Pending Tests (Testing Debt)
*   `test_admin_tournament_filter_persistence`: Ensure search/status filters remain set after page refresh.
*   `test_custom_pagination_rendering`: Verify the dark-neon styled pagination component renders correctly.
*   `test_admin_frequency_tab_functionality`: Verify the tournament admin list filters correctly by Daily/Weekly/Monthly.
*   `test_admin_navigation_flow`: Verify `wire:navigate` SPA-like transitions between list and create/edit pages.

## 🛠️ Feature Gaps & Unused Schema
*   **Missing Features**:
    *   None currently tracked in this document.
*   **Unused Schema Columns**:
    *   `system_settings.category`: Settings are currently a flat list; no categorization UI.
    *   `job_execution_logs.performance_metrics`: JSON field for tracking job speed/memory usage currently empty.
