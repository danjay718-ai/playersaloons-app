# User Flow: Player Onboarding & Identity

This document outlines the step-by-step flow for a player joining the platform, from registration to KYC compliance.

## 1. Registration
Players sign up for an account to begin their journey.

*   **Action**: Player fills out the registration form.
*   **UI Component**: `app/Livewire/Auth/Register.php`
*   **View**: `resources/views/livewire/auth/register.blade.php`
*   **Consent Requirements**: Submit stays disabled until required account fields are filled, passwords match, the player accepts the Cookie Policy, Terms & Conditions, and Privacy Policy, and confirms they are 18 years or older. Newsletter/platform update subscription is optional and currently records opt-in only; sending newsletters is deferred.
*   **Logic (Actions)**:
    *   `app/Modules/Identity/Actions/RegisterUserAction.php`: Atomically creates User, Profile, stores registration consent timestamps/newsletter preference, and assigns the `PLAYER` role. `UserRegistered` is dispatched **after** the transaction commits to guarantee queued listeners can query committed data.
    *   `app/Livewire/Auth/Register.php`: Sends Laravel's email verification notification after registration, logs the user in, and sends them to `/verify-email` instead of the dashboard.
*   **Connected Files**:
    *   `app/Modules/Identity/Models/User.php`: The custom User model.
    *   `app/Modules/Identity/Events/UserRegistered.php`: Dispatched upon successful registration.
    *   `app/Modules/Wallet/Listeners/CreateWalletListener.php`: Subscribes to `UserRegistered` to create the player's initial wallet (runs on `wallet` queue).
    *   `database/migrations/0001_01_01_000000_create_users_table.php`
    *   `database/migrations/2026_07_05_000000_add_registration_consent_fields_to_users_table.php`

## 2. Email Verification
Ensures the player's email is valid and owned by them.

*   **Action**: Player clicks the signed verification link sent to their email. Until verified, authenticated player routes redirect to `/verify-email`.
*   **UI Component**: `app/Livewire/Auth/EmailVerification.php`
*   **Profile Surface**: `app/Livewire/Profile/ProfileDashboard.php` also shows a verified email field and verify button, backed by `users.email_verified_at`.
*   **Connected Files**:
    *   `app/Modules/Identity/Events/EmailVerified.php`: Dispatched upon successful verification.
    *   `app/Shared/Events/DomainEvent.php`: Base event class.
    *   `routes/web.php`: Defines `/email/verify/{id}/{hash}` as a signed verification route and applies `verified` middleware to player routes.
    *   `app/Notifications/Auth/VerifyEmailNotification.php` and `resources/views/emails/auth/verify-email.blade.php`: Lightweight PlayerSaloons-branded verification email.

## 3. KYC Submission
Identity verification is required for financial transactions (e.g., withdrawals).

*   **Action**: Player uploads identity documents (ID, Passport, or Driver's License).
*   **UI Component**: `app/Livewire/Profile/ProfileDashboard.php`
*   **View**: `resources/views/livewire/profile/profile-dashboard.blade.php`
*   **UX Surface**: The profile page shows verified/not verified status and a hover info icon explaining that KYC is required for withdrawals. The upload form lives inside an Alpine-powered drawer opened from the KYC status card, avoiding a Livewire round trip just to open or close the drawer. File selection is echoed immediately with the selected document name and Livewire upload progress so players are not waiting on a delayed upload UI.
*   **Logic (Actions)**:
    *   `app/Modules/Identity/Actions/SubmitKycAction.php`: Handles file uploads and state transitions. Accepted document types: `passport`, `id_card`, `drivers_license`.
*   **Connected Files**:
    *   `app/Modules/Identity/Models/KycSubmission.php`: Model for storing KYC data.
        *   KYC documents are persisted in `document_paths`; the model exposes `document_front_path` and `document_back_path` accessors so admin review screens can display uploaded files without duplicating storage columns.
    *   `app/Modules/Identity/StateMachines/KycStateMachine.php`: Governs the transition from `NOT_SUBMITTED` to `SUBMITTED`.
    *   `app/Modules/Identity/Events/UserKycSubmitted.php`: Dispatched after submission. Handled by `NotifyAdminsOfKycSubmissionListener` (notifies all ADMIN/SUPER_ADMIN users via in-app notification).
    *   `database/migrations/2026_06_07_100002_create_kyc_submissions_table.php`

## 4. Profile Updates
Managing display name, bio, and preferences.

*   **Action**: Player updates their public-facing profile information.
*   **UI Component**: `app/Livewire/Profile/ProfileDashboard.php`
*   **View**: Game-style player card with Alpine-powered profile, account, security, and comms tabs. Tab switching is client-side, profile picture upload lives in the Profile tab with immediate selected-file/progress feedback, and timezone selection uses a Redis-cached curated option list plus the player's existing timezone.
*   **Logic (Actions)**:
    *   `app/Modules/Identity/Actions/UpdateProfileAction.php`: Updates bio, country, timezone, etc.
    *   `app/Modules/Identity/Actions/UploadAvatarAction.php`: Handles profile picture updates.
*   **Connected Files**:
    *   `app/Modules/Identity/Models/UserProfile.php`: Model for profile details.
    *   `app/Modules/Community/Services/NotificationService.php`: Updates notification preferences from the profile dashboard.

## 5. Player Notifications
Players receive in-app notifications from tournament, wallet, KYC, and broadcast flows.

*   **Action**: Player opens the notification bell in the dashboard topbar.
*   **UI Component**: `app/Livewire/Notification/NotificationBell.php`
*   **View**: `resources/views/livewire/notification/notification-bell.blade.php`
*   **Layout Surface**: `resources/views/components/layouts/dashboard.blade.php`
*   **Logic**:
    *   Loads the latest 10 authenticated-user notifications.
    *   Shows unread state and unread count.
    *   Marks one notification or all notifications as read through the authenticated user's `notifications()` relationship.
    *   Refreshes after frontend realtime events via `notification.received`.
*   **Connected Files**:
    *   `resources/js/app.js`: Boots Laravel Echo/Reverb and listens on `user.{uuid}` for `.notification.received`.
    *   `app/Modules/Community/Models/Notification.php`: Factory-enabled model for in-app notification records.
    *   `tests/Feature/Community/NotificationBellTest.php`: Covers list rendering, unread count, mark-as-read behavior, ownership guard, and realtime refresh dispatch.

## 🧪 Isolated Test Cases
To ensure flow integrity, the following tests must be implemented and passing:

### 1. Registration Tests — `tests/Feature/Identity/RegisterUserActionTest.php`
*   **Success**: `test_player_can_register_successfully`
    *   Assert `users` table has entry.
    *   Assert `user_profiles` table has linked entry.
    *   Assert role `PLAYER` is assigned.
    *   Assert `UserRegistered` event dispatched with correct `userId`, `email`, `username`.
*   **Wallet**: `test_wallet_is_created_after_registration`
    *   Assert `wallets` table has entry with `cached_balance = 0.00`.
*   **Validation**: `test_registration_fails_with_invalid_email`
*   **Duplicate**: `test_registration_fails_with_existing_username`
*   **Consent Required**: `test_registration_requires_policy_acceptance_and_age_confirmation`
*   **Consent Stored**: `test_registration_stores_policy_age_and_newsletter_consent`

### 2. KYC Tests — `tests/Feature/Identity/SubmitKycActionTest.php`
*   **Success**: `test_player_can_submit_kyc_documents`
    *   Assert `kyc_submissions` status is `SUBMITTED`.
    *   Assert files are stored on the `local` disk.
    *   Assert `document_front_path` resolves to the first uploaded file for admin review display.
    *   Assert `UserKycSubmitted` event dispatched.
*   **Unauthorized**: `test_player_cannot_submit_kyc_twice_while_pending`
*   **State Machine**: `test_kyc_transition_from_rejected_to_submitted`
    *   Assert resubmission reuses the same `kyc_submissions` row (no duplicate created).
    *   Assert `review_notes` is cleared on resubmission.

### 3. Profile Dashboard Tests — `tests/Feature/Identity/ProfileDashboardTest.php`
*   **Render**: Confirms the game-style player profile renders with KYC status and without the inline KYC upload form.
*   **Profile**: Confirms public profile fields persist to `user_profiles`.
*   **Account**: Confirms username/email changes persist and email changes clear `email_verified_at`.
*   **Verification**: Confirms profile email verification sets `email_verified_at` and dispatches `EmailVerified`.
*   **Security**: Confirms password changes require the current password and persist through Laravel hashing.
*   **KYC Drawer**: Confirms KYC verification UI opens only inside the drawer surface.
*   **Comms**: Confirms Email, In-App, and Realtime preference toggles persist to `notification_preferences`.
*   **Two-factor authentication**: The Security tab generates a TOTP setup key, requires a valid authenticator code before activation, displays eight recovery codes once, and requires the current password to disable 2FA. Login pauses in a guest-safe pending state at `/two-factor-challenge`; a valid TOTP or unused recovery code completes authentication.

## 5. Compliance Access Controls
Admins manage auditable player restrictions at `/admin/compliance`. A block records category, evidence/reason, creator, optional expiry, and revocation details. `EnsureNotComplianceBlocked` protects verified player routes; expired and revoked records do not restrict access, and administrator accounts cannot be blacklisted through the action.

## 🛠️ Feature Gaps & Unused Schema
*   **Missing Features**:
    *   **Referral System Logic**: The referral integer ID is in the DB but the logic to reward referrers is not yet implemented. This is intentionally deferred from the current Phase 2 scope because it is a growth/marketing feature rather than a launch-critical player workflow.
    *   **Social Login**: `provider_name` and `provider_id` are in some variations of the plan but not yet in the current migration.
*   **Unused Schema Columns**:
    *   `user_profiles.metadata`: JSON field currently empty/not used by `UpdateProfileAction`.
