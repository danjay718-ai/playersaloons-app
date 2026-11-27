# Finance, Announcements, Popups & Support Operations (Admin Guide)

## Deposits, Withdrawals & KYC
- **Deposits History (`/admin/deposits`):** Read-only financial monitor with live auto-refresh, summary totals (completed deposits, net credited, fees, pending/failed counts), and tabs for **Completed & Settled Deposits** vs **Live Pending & Failed Checkouts**, filterable by search, status, provider (Stripe/PayOp), and date range.
- **Withdrawals (`/admin/withdrawals`):** Review pending payout requests, verify KYC status, and approve or reject withdrawals (with PayOp payout integration where configured).
- **Wallets & Transactions / Escrow Cashflow:** Audit wallet ledgers, prize payouts, refunds, and platform fee balances.
- **KYC Submissions (`/admin/kyc`):** Inspect uploaded identity documents and approve or reject verification requests.

## Broadcast Notifications & "What's New" Popup Modals
- Open **Notifications** (`/admin/notifications`) and click **New Broadcast**.
- Choose **Display Mode**:
  - **Dashboard Feed Only:** Appears in the Player Dashboard's Official Feed panel.
  - **What's New Popup Modal:** Pops up as a dismissible announcement modal for news, partnerships, updates, or videos.
  - **Both Feed & Popup Modal:** Shows in both places.
- For popups, configure:
  - **Show Popup On:** Homepage Only, Player Dashboard Only, or Both.
  - **Badge Label:** e.g. `What's New`, `New Partnership`, `Update Is Here`.
  - **Media Type:** Text Only, Image Banner (URL or upload up to 4MB), or Video (YouTube/Vimeo link, direct MP4/WebM URL, or upload up to 20MB).
  - **Video-Only Popup:** Check this when showing a promo/announcement video without needing body copy.
  - **CTA Button & Schedule:** Optional button label/link and optional `Starts At` / `Ends At` active window.
- Use **Expire** to immediately stop showing an active popup/broadcast.

## Support Inquiries & Match Reports
- Open **Contact Inquiries** (`/admin/contact-inquiries`) to review player support tickets as well as post-match **Report Match / Provide Feedback** petitions submitted from completed Match Rooms.
