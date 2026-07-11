# PlayerSaloons — Carry Forward Summary
**As of**: 2026-07-12 | **Current version**: v1.106 | **Branch**: `main`

---

## ✅ State ng Project

- Production deployed sa `https://app-testing.website` via Docker Compose + Coolify (Linode)
- SSL active (HTTPS via Let's Encrypt), login working, Horizon active
- PHPStan Level 5 was previously clean on feature work; later environment runs exited with code 1 without diagnostics/output
- `predis/predis` installed — local dev gumagamit ng `REDIS_CLIENT=predis`
- Match confirmation flow now uses canonical `WAITING_FOR_CONFIRMATION`; `RESULT_SUBMITTED` remains legacy-compatible only
- Welcome page header now shows logo image only, without adjacent `PLAYERSALOONS` text
- H2H MVP is now DB-backed with challenge queue, stake lock, match acceptance, result submit/confirm, and winner payout
- H2H result/dispute proof uploads and admin dispute resolution are implemented in `/admin/matches`
- H2H now shows friendly wallet/balance errors and existing users missing wallet rows were backfilled
- H2H timeout policy is conservative: expired waiting challenges refund; stale active/submitted matches go to admin review, never auto-win
- H2H page now has separate Initiate, Open Challenges, Active Duels, and History tabs filtered by selected game, with same-game duplicate waiting/active duel guards and a dashboard-wide duel/invite modal prompt
- H2H UX was refined with an Initiate Challenge drawer, highlighted game filter, global Active Duels badge, conditional tab queries, and removal of cached Eloquent collections that caused Redis `__PHP_Incomplete_Class` errors
- Player tournament history detail pages now load completed/cancelled/refunded tournaments while keeping draft tournaments hidden
- Player wallet deposits now use Stripe Checkout; signed Stripe webhooks credit the ledger-backed wallet after payment success
- Player-facing flash responses now render through shared toasts, player Livewire submit buttons disable during submit, uncached player route changes show a game-like full-page loader, and admin KYC review can display uploaded KYC files through `KycSubmission` document path accessors
- Player avatar/KYC uploads now show immediate selected-file feedback and upload progress before the Livewire request completes
- The PWA service worker no longer cache-first serves HTML/navigation requests, preventing stale unstyled landing-page HTML after logout/logo navigation
- Public/guest pages now share one navbar/footer shell with welcome (`public-navigation`, `public-footer`)
- Guest/public layouts now keep the shared footer in the layout layer, including landing and policy pages, so footer links do not drift between pages
- Registration now requires policy acceptance and 18+ confirmation, records consent timestamps/IP/user agent, and stores optional newsletter/update opt-in without sending newsletters yet
- New registrations must verify email through a signed email link before entering verified player routes; forgot password now sends real reset email links
- Auth emails use lightweight PlayerSaloons-branded verification and password reset templates instead of Laravel defaults
- Transactional mail now uses Laravel `failover` with Resend first, SMTP second, and `log` last; set `RESEND_API_KEY` and use a verified-domain `MAIL_FROM_ADDRESS`
- Login, registration, password reset, and verification resend actions disable their buttons during submit to prevent repeated clicks
- Contact inquiries are now live at `/contact` for guests/players and `/admin/contact-inquiries` for staff review, notes, resolve, and archive
- Admin sidebar is grouped into Operations, CMS, and System; the CMS section contains Blog & News, Landing Page, Games, Platforms, Navigation, Policies, and Translations
- CMS section links are real section URLs (`/admin/cms/content`, `/admin/cms/landing`, `/admin/cms/games`, `/admin/cms/platforms`, `/admin/cms/navigation`) and `CmsAdmin::render()` only loads the active section data
- Blog/News/Page authoring is handled by dedicated `CmsContentAdmin` with a WordPress-style left content list, right inline Quill editor, uploaded featured images, and a fallback textarea so the Body field remains editable while Quill initializes
- Rendered HTML translation now falls back to the original text if a translation lookup returns a non-scalar value, preventing `htmlspecialchars()` array crashes
- CMS Blog and News pages are live at `/blog` and `/news`, with article authoring handled through the Blog & News admin sidebar item (`/admin/cms/content`) using the existing `cms_pages` tables
- Landing page is now DB-backed through `landing_sections` and `landing_section_items`, with admin editing in `/admin/cms`, a `/compressed_v1.mp4` video hero, active game cards, editable cards/reviews/footer, live computed stats, and weekly top-player spotlight
- Landing games now render as a horizontal snap-scroll carousel; `games.banner_path` stores optional per-game card banners, and the landing background uses lightweight CSS-only game patterns
- Landing page has a mobile responsiveness pass for player-heavy mobile traffic: tighter hero/CTA spacing, smaller carousel cards, touch-friendly sections, wrapped footer text, and a compact public mobile nav
- Public navbar items are now editable through `/admin/cms` → Navigation, backed by `public_navigation_items`; mobile burger contains nav items and install action while the mobile topbar stays focused on auth actions
- Legal/policy content now lives in dedicated `policy_pages`, edited at `/admin/policies`, and rendered publicly at `/policies` and `/policies/{slug}`
- Landing page now has a fixed, scroll-aware public navbar over the hero video, horizontal overflow containment, and a JS replay fallback for the hero video loop (`#hero-video`)
- Player dashboard desktop sidebar is viewport-fixed and the main content reserves the collapsed sidebar width, so the sidebar bottom actions remain pinned while long content scrolls
- `/chat` is now a persisted Reverb-backed comms hub for global, direct player-to-player, and active team channels, with unread state, player search/profile/follow actions, global retention capped to the latest 100 messages, and resilient message saves when realtime broadcasting is unavailable
- PWA install support is present through manifest/service worker/icons and native browser install prompt handling
- Echo/Reverb frontend setup is lazy-loaded only for authenticated pages with `meta[name="user-uuid"]`, preventing guest-page WebSocket console errors

---

## ✅ Natapos ngayong session (v1.30–v1.90)

| Version | Item |
|---|---|
| v1.30 | SSL env vars updated sa Coolify |
| v1.31 | `NotifyAdminsOfKycSubmissionListener` — admin notified on KYC submit |
| v1.32 | Security tests — Join button, listing filter, viewRestrictedDetails policy |
| v1.33 | Online presence tracking (Redis middleware + `User::isOnline()` + dot indicator sa UserAdmin) |
| v1.34 | Fix: missing `last_login_at` migration |
| v1.35 | Broadcast Notification Admin Panel (`/admin/notifications`) |
| v1.36 | Player Notification Bell — DB-backed + realtime refresh |
| v1.37 | Match confirmation flow alignment — `WAITING_FOR_CONFIRMATION` canonical state |
| v1.38 | Welcome logo text cleanup — removed visible header wordmark beside logo |
| v1.39 | H2H Production MVP — persisted queue, stake lock/payout, submit/confirm result |
| v1.40 | H2H proof upload + admin dispute review — award creator/opponent or void/refund |
| v1.41 | H2H wallet error handling + wallet backfill for existing users |
| v1.42 | Wallet deposit UI refresh — balance/ledger updates without browser refresh |
| v1.43 | H2H timeout policy — waiting challenge refund, stale duels escalate to admin review |
| v1.44 | Player profile redesign + KYC drawer |
| v1.45 | Player profile tabs + render optimization |
| v1.46 | Player profile client-side interaction optimization |
| v1.47 | Mobile player dashboard navigation redesign |
| v1.48 | S3 avatar upload support |
| v1.49 | UI harmonization + secure KYC storage follow-ups |
| v1.50 | PWA install support — manifest, service worker, icons, native install prompt |
| v1.51 | Shared public shell — welcome and guest/public pages use common navbar/footer |
| v1.52 | PWA/Reverb console cleanup — mobile-web-app meta + lazy authenticated Echo setup |
| v1.53 | Stripe Checkout wallet deposits — hosted Checkout redirect, signed webhook fulfillment, Coolify env docs |
| v1.54 | Player toasts/loading states + KYC admin document display fix |
| v1.55 | Player upload feedback, cached game-style navigation loader, PWA landing HTML cache fix, and restored mobile bottom nav sizing |
| v1.56 | H2H game-filtered tabs, same-game duplicate duel guards, wrong-game accept rejection, and dashboard-wide duel prompt |
| v1.57 | H2H UI/UX redesign + conditional tab query optimization |
| v1.58 | H2H initiate drawer, highlighted game filter, global active duels badge, and Redis collection cache fix |
| v1.59 | Tournament history detail access fix for completed/cancelled/refunded tournaments |
| v1.60 | Dynamic DB-backed landing page and `/admin/cms` landing editor |
| v1.61 | Landing game carousel, lightweight background patterns, and editable game banners |
| v1.62 | Landing mobile responsiveness pass and compact mobile public nav |
| v1.63 | DB-backed public navigation items and `/admin/cms` navigation editor |
| v1.64 | Landing redesign, fixed scroll-aware public nav, and horizontal overflow containment |
| v1.65 | Hero video loop reliability fallback |
| v1.66 | Documentation synchronization pass for routes, versions, stale references, and wallet/withdrawal flow docs |
| v1.67 | Dedicated database-backed policy pages with admin editor and public guest views |
| v1.68 | Added Terms and Conditions to the dedicated policy pages |
| v1.69 | Admin translation manager and runtime localization |
| v1.70 | Related CMS seed flow |
| v1.71 | Production Composer build fix |
| v1.72 | Production startup CMS seeding fix |
| v1.73 | Registration consent and Join Now form redesign |
| v1.74 | Email verification, forgot password email, and newsletter deferral |
| v1.75 | Local registration migration and auth icon refresh fix |
| v1.76 | Auth form double-submit guard |
| v1.77 | Branded lightweight auth email templates |
| v1.78 | Contact inquiries public form and admin inbox |
| v1.79 | Landing footer Contact link correction, later superseded by shared footer consolidation |
| v1.80 | Shared guest footer consolidation |
| v1.81 | Contact inquiry resolve/archive UX fix |
| v1.82 | CMS Blog/News public pages and article authoring |
| v1.83 | Blog & News admin sidebar visibility fix |
| v1.84 | CMS admin sidebar grouping |
| v1.85 | Admin sidebar translation crash fix |
| v1.86 | CMS section pages and active-section-only rendering |
| v1.87 | WordPress-style Blog/News editor and dedicated content Livewire component |
| v1.88 | CMS Body rich editor typing fix with guarded Quill initialization |
| v1.89 | Restored Quill 1.3 admin editor compatibility and kept CMS Body fallback textarea |
| v1.90 | Resend transactional mail failover |
| v1.91 | Player/tournament stream embed integration with normalized stream channels |
| v1.92 | Real-time stream chat and Twitch-style streams redesign |
| v1.94 | Player desktop sidebar viewport pinning fix |
| v1.95 | Realtime global, direct, and team chat |
| v1.96 | Chat send resilience when realtime broadcasting is unavailable |
| v1.97 | Broadcast socket ID hardening and resilient stream chat broadcasts |
| v1.98 | Player tournament Livewire coverage: elimination, stats/history, filtering, and N+1 guard |
| v1.99 | Compliance/blacklisting, authenticator 2FA, per-game H2H ELO, and provider live-status polling |

---

## 📋 Full Pending Backlog

See `documentation/execution_checklist.md` for complete list. Summary:

| Priority | Item | Effort |
|---|---|---|
| ✅ | Contact inquiry workflow polish | Done v1.101 |
| ✅ | Newsletter management and sending | Done v1.102 |
| ✅ | Referral system logic | Done v1.103; first-deposit qualification v1.104 |
| ✅ | Deposit processing fee | Done v1.105 |
| ✅ | Advertisement and promotion management | Done v1.106 |
| 5–7 | Team tournaments, auto-forfeit setting, rematch voting | Follow-up feature gaps, in this order |
| 🟡 | Remaining testing debt | Admin/player frequency filters, filter persistence, pagination, and admin navigation |
| ⚪ | R2 storage and external payout integration | Production readiness; deferred during testing |

### Already Done / Do Not Re-open

- `last_login_at` login update and migration — done v1.29/v1.34
- `UserKycSubmitted` admin listener — done v1.31
- Broadcast Messages admin UI — done v1.35
- Player notification bell — done v1.36
- Match confirmation state mismatch — fixed v1.37
- H2H mock-only challenge queue — replaced with DB-backed MVP v1.39
- H2H proof upload + admin dispute review — done v1.40
- H2H raw missing-wallet exception on find duel — fixed v1.41
- H2H timeout/auto-expiry policy — done v1.43
- H2H mixed open/active/history view and missing same-game duplicate guards — fixed v1.56
- H2H slow tab load/repeated query UX — improved v1.57
- H2H global active duel visibility and cached Eloquent collection Redis error — fixed v1.58
- Player cannot view completed tournament details from history — fixed v1.59
- Wallet mock deposit stale UI balance after success — fixed v1.42
- Wallet mock-only deposit flow — replaced with Stripe Checkout + webhook fulfillment v1.53
- Player inline flash alerts — replaced with shared toast surface v1.54
- KYC admin missing uploaded document display — fixed with `document_front_path` / `document_back_path` accessors v1.54
- Generic player button preloaders — replaced with submit-button disable-only behavior and cached full-page navigation loader v1.55
- Landing page stale/unstyled after logout/logo click — fixed by removing HTML from service worker cache-first handling v1.55
- Mobile bottom navigation felt too small — restored larger tap target/icon/label sizing v1.55
- Desktop player sidebar bottom created whitespace on long content scroll — fixed with viewport pinning and content offset v1.94
- Public/guest nav/footer mismatch with welcome — fixed v1.51
- Landing/policy footer drift from shared guest footer — consolidated through layout-level shared footer v1.80
- Contact inquiry resolve/archive state looked unchanged in admin after action — fixed v1.81
- `/chat` mock-only session messages — replaced with persisted Reverb-backed comms hub v1.95
- `/chat` message send failed when Reverb/Pusher was unavailable — fixed v1.96
- Compliance/blacklisting middleware and admin page — done v1.99
- Authenticator 2FA with recovery codes — done v1.99
- Per-game H2H ELO and skill-aware matchmaking — done v1.99
- Provider live-status polling — done v1.99
- Player tournament elimination/stats/history/filter/N+1 tests — done v1.98
- Static landing page requiring code edits for content changes — replaced with DB-backed landing CMS v1.60
- Landing games grid without visual game banners — improved with horizontal carousel and `games.banner_path` v1.61
- PWA install CTA placement and mobile duplication — fixed v1.50/v1.51
- Guest-page Reverb WebSocket console spam — fixed v1.52

### H2H Follow-up Scope

- Monitor rating distribution and match wait times before tuning the 100-to-400 point widening window or K-factor.

---

## 📁 Documentation Structure

```
README.md                                  ← project intro
documentation/
  ONBOARDING.md                            ← start here, conventions, doc update format
  FEATURE_MAP.md                           ← all routes, components, backend mapping, tests
  architecture_baseline.md                 ← why things were built a certain way
  execution_checklist.md                   ← backlog, testing debt, known bugs
  project_progress.md                      ← version history, deployment notes
  guides/
    r2-storage-migration.md                ← step-by-step R2 migration
  01_identity_onboarding.md
  02_tournament_lifecycle.md
  03_financial_operations.md
  04_team_management.md
  05_admin_operations.md
```

---

## 🤖 How to Start Next Convo with AI

**For a specific feature/bug**, use this format:
```
Basahin mo ang:
- documentation/ONBOARDING.md
- documentation/FEATURE_MAP.md
- documentation/0X_module.md  ← relevant module lang

[Describe the task]

Requirements:
- PHPStan Level 5 minimum after changes
- Test case required
- Update docs per ONBOARDING.md conventions
```

**For continuing backlog work**:
```
Basahin mo ang documentation/ONBOARDING.md at documentation/execution_checklist.md.
I-implement natin ang [item from checklist].
```
