# Admin Roles, Access & Operations Overview (Admin Guide)

## Staff Roles & Permissions
GamersRival uses role-based access control across the Admin Control Terminal:
- **Super Admin:** Full system access, including system settings, AI & API credentials, role management, recoverable deletions, and financial/compliance overrides.
- **Admin:** Full operational management of tournaments, matches, disputes, users, broadcasts, and settings (excluding Super Admin-only deletions).
- **Tournament Organizer:** Creates and manages tournaments, schedules, brackets, and match oversight.
- **Moderator:** Reviews player reports, chat moderation, and match disputes.
- **Finance Operator:** Manages Deposits History, Withdrawals approval/payouts, Wallets & Transactions, and Escrow/Cashflow monitoring.
- **KYC Reviewer:** Reviews and approves/rejects player identity verification submissions.
- **Support Agent:** Handles Contact Inquiries (including player match reports/appeals) and player assistance.

## Compliance & Accountability
- Every sensitive admin action (settings changes, dispute rulings, payouts, bans, strikes) is recorded in **Audit Logs** and **Staff Activity**.
- **Compliance & Strikes:** Admins can issue timed compliance blocks or V2 dishonest-result strikes (with optional balance deductions) when confirmed false match evidence is submitted. A player's 3rd confirmed strike schedules a permanent ban after their active competition finishes.
