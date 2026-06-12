# PlayerSaloons — New Admin Features & Missing Requirements Implementation Plan v1
**Status: APPROVED — Added to Post-MVP Backlog**
**Date: 2026-06-13**

This document details the architectural design and implementation checklist for the new admin, compliance, and legal features. As decided, these features are listed in the backlog and plan but will be implemented after the core MVP phases.

---

## Technical Context & Architectural Design

To support these new features without breaking the existing Modular Domain Design:
1. **CMS Module Expansion**: The `CMS` module will be extended to include `Blog/News` and `Platforms` (game platform categorization like Mobile, PC, Console, Xbox).
2. **Operations & Compliance Module**: A new `Compliance` domain under `app/Modules/Operations` will hold blocked countries, IP filtering, and legal document layouts.
3. **Double-Entry Ledger Integrity**: Deposits will be viewable in a read-only list interface (`DepositAdmin`), sourcing data directly from the read-only `deposits` and `ledger_entries` tables.
4. **Rich WYSIWYG Editor**: Integrates **Trix** or **Quill** editors via AlpineJS directly into Livewire forms for rich text formatting of CMS Pages and Blogs.
5. **Drawer layouts**: The existing `WithdrawalAdmin` details panel will be redesigned into a right-aligned sliding viewport Drawer (using Tailwind CSS slide-overs) to display ledger histories, KYC files, and audit trails on a single full-height layout.

---

## Explanations & Technical Breakdown

### CMS Page Rendering & Guest Page Footer
*   **How CMS works**: The CMS pages in the DB contain unstructured HTML content managed by the admin using the rich editor.
*   **Routing**: We will define a dynamic route `/pages/{slug}` that maps to a public Livewire page component `ShowCmsPage`.
*   **Design/Styling**: The `ShowCmsPage` view will pull the page content and render it inside a premium, glassmorphic layout container with Tailwind CSS typography utilities (`prose prose-invert max-w-4xl mx-auto`).
*   **Footer Links**: The page layout footer in `welcome.blade.php` and `app.blade.php` will dynamically query the database for published CMS pages categorized under specific headers (e.g., `legal`, `support`, `info`) and print their links dynamically.

### Blacklisting / Blocked Countries
*   **Table Structure**: A new `blocked_countries` table storing `country_code` (ISO-2) and `reason`.
*   **Verification**: A `CheckBlockedCountry` middleware will check incoming IP requests (via Cloudflare headers `HTTP_CF_IPCOUNTRY` or a GeoIP library) or check the user's profile country at login/registration. Users from blocked countries will be redirected to a compliance warning page.

### Notifications Triggering & Administration
*   **Per-User Notification System**:
    1.  **Triggers**: System events trigger notifications automatically via event listeners subscribing to domain events (e.g., `UserRegistered` -> `CreateWalletListener`, `MatchCompleted` -> `NotifyParticipantsListener`).
    2.  **Notification Preference check**: Event listeners delegate to `NotificationService::send()`, which checks the user's preferences in `notification_preferences` (`email_enabled`, `in_app_enabled`, `realtime_enabled`).
    3.  **Real-Time Broadcast**: If enabled, the service broadcasts via Laravel Reverb using WebSockets on the `user.{uuid}` channel.
*   **Admin Panel Requirement**: The new admin notifications console will allow:
    *   **Global Broadcasts**: Admins write a notification and queue it to be sent to all active users via a background job, bypassing individual silence toggles for critical system announcements.
    *   **User Log Audit**: A search-by-user panel to inspect what notifications were dispatched to a player to troubleshoot delivery claims.

### Translation Management (Localizing Static UI Content)
*   **Static vs. Dynamic Content**: Dynamic content (games, CMS pages) uses translation tables. Static content (button texts, navigation labels, alerts) uses Laravel's language json files (e.g., `lang/en.json` and `lang/tl.json`).
*   **Translation Panel**: An admin screen reading these JSON language files, rendering key-value inputs in a searchable table. When saved, the JSON files are rewritten, instantly localizing the static text across the website.

---

## Proposed Database Schema Changes

### [NEW] `create_platforms_table`
Stores game platform classifications.
*   `id` (BigInt, PK)
*   `uuid` (UUID, Unique)
*   `slug` (String, Unique)
*   `name` (String)
*   `icon` (String, nullable) - Lucide icon name (e.g., 'smartphone', 'gamepad')
*   `is_active` (Boolean, default: true)
*   `timestamps`

### [MODIFY] `tournaments` & `tournament_templates` tables
Add platform constraint columns.
*   `platform_id` (BigInt, FK referencing `platforms.id`, nullable)

### [NEW] `create_posts_and_translations_table`
For the blog management system.
*   `posts` table:
    *   `id` (BigInt, PK)
    *   `uuid` (UUID, Unique)
    *   `slug` (String, Unique)
    *   `author_id` (FK to `users`)
    *   `published_at` (Timestamp, nullable)
    *   `timestamps`
    *   `deleted_at` (SoftDeletes)
*   `post_translations` table:
    *   `id` (BigInt, PK)
    *   `post_id` (FK to `posts`)
    *   `locale` (Char 5)
    *   `title` (String)
    *   `excerpt` (Text, nullable)
    *   `content` (LongText)
    *   `timestamps`
    *   `unique(['post_id', 'locale'])`

### [NEW] `create_tournament_reviews_table`
For review and rating management.
*   `id` (BigInt, PK)
*   `uuid` (UUID, Unique)
*   `user_id` (FK to `users`)
*   `tournament_id` (FK to `tournaments`)
*   `rating` (UnsignedInteger, 1 to 5)
*   `comment` (Text, nullable)
*   `is_approved` (Boolean, default: true)
*   `timestamps`

### [NEW] `create_contact_inquiries_table`
For the contact inquiry management system.
*   `id` (BigInt, PK)
*   `uuid` (UUID, Unique)
*   `name` (String)
*   `email` (String)
*   `subject` (String)
*   `message` (Text)
*   `status` (String, e.g. `pending`, `resolved`)
*   `resolved_by` (FK to `users`, nullable)
*   `resolution_notes` (Text, nullable)
*   `timestamps`

### [NEW] `create_newsletter_subscribers_table`
For the newsletter subscription system.
*   `id` (BigInt, PK)
*   `email` (String, Unique)
*   `is_active` (Boolean, default: true)
*   `token` (String, Unique, nullable) - for unsubscribe verification
*   `timestamps`

### [NEW] `create_blocked_countries_table`
For the country/IP blacklist compliance system.
*   `id` (BigInt, PK)
*   `country_code` (Char 2, Unique) - e.g., 'US', 'KP'
*   `reason` (String, nullable)
*   `timestamps`

---

## Proposed Code Changes

### Identity & Compliance Domain

#### [NEW] `CheckBlockedCountry` Middleware
*   Middleware verifying the visitor's country. It inspects standard IP geography headers (e.g., Cloudflare's `HTTP_CF_IPCOUNTRY`) against the `blocked_countries` table. Redirects blocked visitors to a `/blocked` compliance information page.

---

### Wallet Domain

#### [MODIFY] `WithdrawalAdmin` Livewire Component & View
*   Refactor the selection modal into a right-aligned sliding side drawer layout. 
*   Expand content to display: user profile avatar, registration age, KYC link, withdrawal request info, and a scrollable list of the user's last 10 double-entry wallet ledger history entries.

#### [NEW] `DepositAdmin` Livewire Component & View
*   Livewire component `/admin/deposits` displaying a read-only list of deposits with filters by payment gateway (provider), amount range, status, and username.

---

### CMS & Content Domain

#### [MODIFY] `CmsAdmin` Livewire Component & View
*   Integrate a rich text editor inside page edit forms.
*   Extend tabs to support managing game platforms (Console, PC, Mobile) alongside games and pages.

#### [NEW] `BlogAdmin` Livewire Component & View
*   Livewire component `/admin/blog` providing full CRUD dashboard for managing blog posts, translations, and scheduled publish states.

---

### Community & Operations Domain

#### [NEW] `ReviewAdmin` Livewire Component & View
*   Livewire component `/admin/reviews` to moderate tournament star ratings and text comments (approve, delete, search by tournament or user).

#### [NEW] `InquiryAdmin` Livewire Component & View
*   Livewire component `/admin/inquiries` listing contact inquiries. Enables admins to inspect submissions, change status to resolved, and save resolution comments.

#### [NEW] `NewsletterAdmin` Livewire Component & View
*   Livewire component `/admin/newsletters` listing active subscribers, and displaying a broadcast panel to draft newsletter campaigns with a rich content editor.

#### [NEW] `NotificationAdmin` Livewire Component & View
*   Livewire component `/admin/notifications` supporting manual broadcast dispatch and notification log inspection for debug purposes.

#### [NEW] `TranslationAdmin` Livewire Component & View
*   Livewire component `/admin/translations` loading JSON file translation keys and writing customized overrides back to the files system.

#### [NEW] `BlacklistAdmin` Livewire Component & View
*   Livewire component `/admin/blacklist` for managing country blacklists and blocked lists.

---

### Guest Layout Updates

#### [MODIFY] `welcome.blade.php` & `app.blade.php`
*   Refactor public footer layouts to query published CMS pages (grouped by footer section headers: Legal, Info, Support) dynamically. Add the newsletter subscription widget input field.

#### [NEW] `ShowCmsPage` Guest Livewire Component & View
*   Guest component `/pages/{slug}` rendering CMS page details with custom typography.
