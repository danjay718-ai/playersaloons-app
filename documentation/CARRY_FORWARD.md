# PlayerSaloons — Carry Forward Summary
**As of**: 2026-06-29 | **Current version**: v1.66 | **Branch**: `main`

---

## ✅ State ng Project

- Production deployed sa `https://app-testing.website` via Docker Compose + Coolify (Linode)
- SSL active (HTTPS via Let's Encrypt), login working, Horizon active
- PHPStan Level 5 was previously clean on feature work; later environment runs exited with code 1 without diagnostics/output, and v1.66 was documentation-only
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
- Landing page is now DB-backed through `landing_sections` and `landing_section_items`, with admin editing in `/admin/cms`, a `/compressed_v1.mp4` video hero, active game cards, editable cards/reviews/footer, live computed stats, and weekly top-player spotlight
- Landing games now render as a horizontal snap-scroll carousel; `games.banner_path` stores optional per-game card banners, and the landing background uses lightweight CSS-only game patterns
- Landing page has a mobile responsiveness pass for player-heavy mobile traffic: tighter hero/CTA spacing, smaller carousel cards, touch-friendly sections, wrapped footer text, and a compact public mobile nav
- Public navbar items are now editable through `/admin/cms` → Navigation, backed by `public_navigation_items`; mobile burger contains nav items and install action while the mobile topbar stays focused on auth actions
- Landing page now has a fixed, scroll-aware public navbar over the hero video, horizontal overflow containment, and a JS replay fallback for the hero video loop (`#hero-video`)
- PWA install support is present through manifest/service worker/icons and native browser install prompt handling
- Echo/Reverb frontend setup is lazy-loaded only for authenticated pages with `meta[name="user-uuid"]`, preventing guest-page WebSocket console errors

---

## ✅ Natapos ngayong session (v1.30–v1.66)

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

---

## 📋 Full Pending Backlog

See `documentation/execution_checklist.md` for complete list. Summary:

| Priority | Item | Effort |
|---|---|---|
| 🟡 | H2H ELO/skill matching | Optional for v1; matchmaker currently uses game/stake/platform/region |
| 🟢 | Referral system logic | Medium |
| 🔵 | 2FA | Large |
| 🔵 | External payout integration | Large + business decision |
| 🔵 | Compliance/blacklisting, contact inquiries, newsletter admin | Medium/Large |
| 🔵 | CMS Blog/News + translation management | Medium |
| 🟡 | Remaining testing debt | Tournament filters, pagination, elimination modal, N+1 checks |
| ⚪ | R2 storage migration | Deferred during testing; Docker volumes are acceptable until full launch |

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
- Public/guest nav/footer mismatch with welcome — fixed v1.51
- Static landing page requiring code edits for content changes — replaced with DB-backed landing CMS v1.60
- Landing games grid without visual game banners — improved with horizontal carousel and `games.banner_path` v1.61
- PWA install CTA placement and mobile duplication — fixed v1.50/v1.51
- Guest-page Reverb WebSocket console spam — fixed v1.52

### H2H Follow-up Scope

- Add optional ELO/skill matching after enough player history exists.

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
