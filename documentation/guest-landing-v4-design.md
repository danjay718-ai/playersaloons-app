# Guest Landing & Public Experience Design Guide — v4 ("Neon Arena")

This document defines the canonical design system, component architecture, interaction rules, and versioning standards for the GamersRival public/guest experience (`v4`). All future guest-facing UI additions or updates should follow these specifications.

---

## 1. Design Direction & Philosophy

- **Concept:** *"Neon Arena"* — reimagining the competitive saloon as a high-energy esports lobby using live match tickets, interactive game tables ("Pick your table"), and verified player wins.
- **Clarity Over Clutter:** Each section communicates **one focused idea** with concise copy and immediate visual hierarchy—avoiding walls of text or monotonous stacked tables.
- **Semantic Energy:** Color is never decorative-only; every accent color signals a specific meaning in the competitive loop.

---

## 2. Color System & CSS Tokens (`resources/css/guest-v4.css`)

All v4 styles are scoped under `.gr-experience` and use `--gr-*` custom properties (with `--guest-*` aliases so shared authentication views automatically inherit the palette):

| Token | Hex / Value | Semantic Role |
| :--- | :--- | :--- |
| `--gr-bg` | `#05080f` | Primary deep arena background |
| `--gr-bg2` | `#0a111d` | Elevated band / navigation drawer / ticker surface |
| `--gr-card` | `#0e1726` | Card and panel base surface |
| `--gr-line` | `rgba(255, 255, 255, 0.10)` | Subtle structural borders and dividers |
| `--gr-ink` | `#f2f7ff` | High-contrast primary text |
| `--gr-muted` | `#a8b6c9` | Secondary copy and metadata labels |
| `--gr-lime` | `#c6ff3d` | **Action & Wins:** Primary CTAs (`Join Now`, `Take a seat`), live status dots, prize badges, entry fees |
| `--gr-cyan` | `#22e6ff` | **Arena Identity:** Eyebrows, game labels, step stroke outlines, secondary hover states |
| `--gr-yellow` | `#ffd23f` | **Time & Rank #1:** Live countdown boxes, time-remaining text, #1 weekly honour highlight |
| `--gr-pink` | `#ff5ad9` | Per-table cycling accent (`.gr-a3`) & stat counter #4 |
| `--gr-orange` | `#ff8a3d` | Per-table cycling accent (`.gr-a4`) |

---

## 3. Typography & Shape Language

- **Display Font (`--font-orbitron`):** Used for `h1`, `h2`, `.gr-footer-brand`, `.gr-ticket-name`, `.gr-stat dd`, `.gr-honour-rank`, and `.gr-step-no`.
  - Hero `h1` is uppercase, `clamp(38px, 6.2vw, 88px)`, `font-weight: 900`, followed by a signature 120px neon-lime slash bar.
- **Body Font (`--font-sans`):** Clean readable copy (`line-height: 1.65`) with `font-variant-numeric: tabular-nums` enforced on all timers, prize figures, and counters.
- **Slash-Cut Buttons (`.gr-btn`):**
  - Chamfered parallelogram silhouette via `clip-path: polygon(12px 0, 100% 0, calc(100% - 12px) 100%, 0 100%)`.
  - `.gr-btn-primary`: Solid `--gr-lime` (`#c6ff3d`) with dark ink (`#0a1204`) and neon drop-shadow on hover.
  - `.gr-btn-ghost`: Translucent glass with inset border and `--gr-cyan` hover state.
- **Live Ticket Silhouette (`.gr-ticket`):**
  - Rounded glass container (`border-radius: 22px`, `border: 1px solid #22e6ff55`) featuring a perforated divider (`.gr-ticket-tear`) with circular side notches (`::before` / `::after`).

---

## 4. Component Architecture (`resources/views/components/guest/`)

The guest landing page (`resources/views/livewire/landing/landing-page.blade.php`) is modularized into reusable anonymous Blade components under `<x-guest.*>`:

| Component | File | Purpose & Key Props |
| :--- | :--- | :--- |
| `<x-guest.button>` | `components/guest/button.blade.php` | Primary/ghost slash-cut CTA (`href`, `variant="primary\|ghost"`, `arrow="↗"`) |
| `<x-guest.section-head>` | `components/guest/section-head.blade.php` | Standardized section header (`eyebrow`, `title`, `body`, `href`, `linkLabel`) |
| `<x-guest.countdown>` | `components/guest/countdown.blade.php` | Live per-second countdown (`:at`, `mode="text\|boxes"`) |
| `<x-guest.hero>` | `components/guest/hero.blade.php` | Full-bleed video hero, cursor-reactive neon glow, intro copy, and live ticket |
| `<x-guest.ticket>` | `components/guest/ticket.blade.php` | Featured upcoming tournament ticket (`:tournament`) with 4-box countdown & `Take a seat` CTA |
| `<x-guest.table-card>` | `components/guest/table-card.blade.php` | Interactive game card (`:game`, `:tournaments`, `:index`) with 3D pointer tilt and expandable upcoming matches drawer |
| `<x-guest.step>` | `components/guest/step.blade.php` | Numbered step item (`number`, `title`, `body`) inside the scroll-progress `<ol class="gr-steps">` |
| `<x-guest.stat>` | `components/guest/stat.blade.php` | Animated count-up metric card (`value`, `label`) |
| `<x-guest.honour>` | `components/guest/honour.blade.php` | Weekly top player card (`:player`) with rank watermark and gold #1 highlight |
| `<x-guest.cta-band>` | `components/guest/cta-band.blade.php` | High-contrast lime-to-cyan gradient closing banner (*"Your seat is open."*) |
| `<x-guest.join-bar>` | `components/guest/join-bar.blade.php` | Fixed mobile bottom bar (`Sign In` + `Join Now`) that appears after scrolling past the hero |

### Shared Layout Partials
- **Header Navigation:** `resources/views/components/layouts/partials/gr-navigation.blade.php` — Features a top neon-gradient accent bar, a floating dark-glass navigation capsule dock (`.gr-nav-links`), **`Home` (`href="/"`)** as the first item with an active glowing neon-lime pill (`.is-active` / `aria-current="page"`), and sticky positioning (`.gr-header { position: sticky; top: 0; z-index: 50; }`) enabled across all layouts via `overflow-x-clip` on `<body>`.
- **Footer Monument:** `resources/views/components/layouts/partials/gr-footer.blade.php`
- **Live Winners Wire:** `resources/views/livewire/landing/guest-pulse-v4.blade.php` (rendered by `App\Livewire\Landing\GuestPulse` when `placement="activity"`) — Displays a pinned **Platform Win Totals** badge (`X won matches · Won: +Currency`) alongside the scrolling feed of recent match winners and their prize payouts.

---

## 5. Interactive Behaviors (`resources/js/guest-v4.js`)

All interactivity is powered by lightweight Alpine.js components with zero external JS library dependencies:

1. **`grTypewriter(words)`:**
   - Splits the hero `h1` into colored segments (`PLAY.` in white, `WIN.` in electric cyan, and `CASH OUT.` in neon lime) and types/cycles the final action phrase (`CASH OUT.` → `DOMINATE.` → `GET PAID.` → `RANK UP.`) with a blinking yellow cursor (`.gr-type-cursor`).
2. **`grHome`:**
   - Tracks pointer coordinates (`--mx`, `--my`) across `[data-gr-hero]` for the interactive neon aura.
   - Toggles `pastHero` via `IntersectionObserver` to reveal the mobile sticky `<x-guest.join-bar />`.
   - Staggers `.gr-reveal` scroll-in animations and triggers cubic-ease-out number count-up on `[data-count]`.
   - Updates `--p` (`0` to `1`) on `[data-gr-steps]` during scroll to fill the connecting neon progress line (horizontal on desktop, vertical on mobile).
2. **`grCountdown(iso, labels)`:**
   - Computes live days, hours, minutes, and seconds every 1,000ms from an ISO-8601 timestamp and displays localized `"Starting now"` upon expiry.
3. **`grTable`:**
   - Applies subtle 3D perspective tilt (`--rx`, `--ry`) on mouse move and toggles `open` on click/tap to reveal up to 2 upcoming tournament slots plus a direct game link.

---

## 6. Responsive & Accessibility Standards

- **Breakpoints:**
  - `> 1100px`: 2-column hero stage (`minmax(0, 1fr) 420px`), full desktop navigation, multi-column auto-fill tables grid.
  - `<= 1100px`: Hamburger menu toggle, stacked hero stage with max-width 520px ticket.
  - `<= 700px`: 2-column game tables grid (`1fr` below `380px`), vertical step progress bar on the left, 2x2 stats grid, and sticky bottom `.gr-joinbar`.
- **Reduced Motion (`prefers-reduced-motion: reduce`):**
  - Disables background video autoplay, 3D card tilt, marquee keyframe animation, and scroll transitions; immediately reveals all `.gr-reveal` elements and fills step progress.
- **Keyboard & Screen Readers:**
  - Visible `2px solid var(--gr-lime)` focus ring on all interactive controls.
  - Skip-to-content link (`.gr-skip`), `aria-expanded` / `aria-controls` on expandable game tables and mobile navigation, and `inert` / `aria-hidden="true"` on the duplicated marquee track.

---

## 7. Required Platform Connections

Every iteration of the guest experience must preserve these hooks:
1. **Registration (`Join Now` / `Create Account`):** Links to `/register` in the top navbar, hero primary CTA, closing CTA band, and mobile sticky join bar.
2. **Authentication (`Sign In`):** Links to `/login` in desktop nav, mobile drawer, closing CTA band, and mobile sticky join bar.
3. **PWA Install (`Install App`):** Buttons with `.pwa-install-btn`, `data-pwa-install-desktop`, `data-pwa-install-mobile`, and child `[data-pwa-install-label]` in the header, mobile menu, closing CTA band, auth header, and footer so `resources/js/app.js` automatically manages install states.
4. **Auth Pages Integration:** `/login` and `/register` (`resources/views/components/layouts/auth.blade.php`) use `.gr-experience.guest-auth-page` and share `gr-footer.blade.php`.

---

## 8. Versioning & Rollback (`v4` vs `v3`)

- Configured in `config/features.php` under `features.landing.version` (`env('LANDING_VERSION', 'v4')`).
- **Default (`v4`):** Renders `landing-page.blade.php` (`gr-experience`, `gr-navigation`, `gr-footer`, `guest-pulse-v4`).
- **Fallback (`v3`):** Setting `LANDING_VERSION=v3` in `.env` restores `resources/views/livewire/landing/landing-page-v3.blade.php` along with `guest-navigation`, `guest-footer`, and `.guest-experience` styles from `resources/css/guest.css`.

---

## 9. Public-Facing Editorial & Information Pages

All public-facing content pages share the `.gr-surface-card` glass surface, `.gr-eyebrow` (`//`) headers, and interactive reader patterns:

- **Updates Command Center (`/updates` — `resources/views/livewire/cms/content-hub.blade.php`):** Interactive category filter pills (`All Updates`, `Official News`, `Community Blog`), instant keyword search, and a 2-column **Interactive Split Quick-Reader** where clicking any dispatch previews its full summary and body on the right without leaving the hub.
- **Blog & News Index (`/blog`, `/news` — `resources/views/livewire/cms/article-index.blade.php`):** Horizontal editorial board with channel switcher tabs, live search, reading-time badges, and an expandable **Inline Quick-Read Drawer** on every article row.
- **Article Detail (`/blog/{slug}`, `/news/{slug}` — `resources/views/livewire/cms/article-view.blade.php`):** Clean Neon Arena reading view with reading-time badge and one-click share/copy link button.
- **Inside GamersRival (`/about` — `resources/views/livewire/about-page.blade.php`):** Interactive **Live Arena Floor Radar** (tap any game node to inspect live open tables, seats, and next match start), live platform proof stats, the live winners ticker, 4 interactive **"How the House Runs"** pillars, the CMS story card, interactive `<x-guest.table-card>` shelf, and `<x-guest.cta-band />`.
- **Interactive Legal & Fair-Play Explorer (`/policies`, `/policies/{slug}` — `resources/views/livewire/policies/`):** Fair-play protection highlights, instant search filter, and a 2-column **Interactive Policy Reader** on `/policies` with a right-side **On This Policy Section Scroll Navigator** + reading progress bar, plus a 3-column layout on `/policies/{slug}` (Left: Sticky Legal Documents Switcher, Center: Numbered Clause Sections, Right: Sticky **On This Page Section Scroll** rail).
- **Contact (`/contact` — `resources/views/livewire/community/contact-page.blade.php`):** Unified under `.gr-surface-card` and `.gr-btn-primary` slash-cut CTAs.
- **Auth Pages (`/login`, `/register` — `resources/views/components/layouts/auth.blade.php`):** Share the full sticky `.gr-header` topbar (`gr-navigation.blade.php`) and `gr-footer.blade.php`.
- **Timezone Synchronization (`App\Modules\Tournament\Services\TournamentTimezone`):** All guest, player, and admin schedule displays (`ticket.blade.php`, `table-card.blade.php`, `about-page.blade.php`, `player-tournament-list.blade.php`, `player-content.blade.php`) use `app(TournamentTimezone::class)->value()` (default `Europe/Amsterdam`) instead of raw `UTC`.

---

## 10. Unified Tournament & Head-to-Head Board + Streamlined Overview (Guest & Player)

Both **Guest** (`/tournaments`, `/h2h?view=guest`, `/games/{slug}?view=guest`) and **Player** (`/player/tournaments`, `/h2h`, `/games/{slug}`, `/player/my-tournaments`, `/head-to-head`) views use a **simplified horizontal Board Row (`flex flex-col gap-2.5`)** paired with an **Interactive Top Spotlight Preview Stage**:

- **Clean Tournament Display Name (`$tournament->display_name`):**
  - `Tournament::getDisplayNameAttribute()` strips appended occurrence timestamps (` - Sep 26, 2026 12:00 PM`) from visible titles on cards, tickets, and headers since schedule details are already shown in the overview.
- **Interactive Top Preview Stage (`resources/views/livewire/tournament/player-tournament-list.blade.php`):**
  - Left (`lg:col-span-7`): **Active Competition Spotlight** with a 24-pip visual seat map, `Entry Fee`, `Prize`, `Full Capacity`, and start time in the platform timezone.
  - Right (`lg:col-span-5`): **"Starting Next — Click to Preview"** interactive queue where clicking any upcoming competition previews it in the spotlight stage immediately.
- **Simplified Single-Line Row (`resources/views/components/player/tournament-card.blade.php`):**
  - Entire card is clickable with a full-card link and a sleek **Arrow (`→`)** on the right that lights up neon-lime on hover of the whole card.
  - No inner field labels, no platform list, no `Registration Open` status badge, and no schedule/start text inside the row (column headers are displayed once in the top Board Header bar: `Competition | Entry Fee | Prize | Full Capacity`):
    1. `52×52` game thumbnail
    2. Clean title (`$tournament->display_name`) + game, competition type, and frequency subtitle
    3. `Entry Fee` (`Free` or formatted currency)
    4. `Prize` (formatted prize pool right beside Entry Fee)
    5. `Full Capacity` (visual seat pips + `X / Y`)
    6. Glowing hover arrow (`→`)
- **Streamlined Tournament Overview (`resources/views/livewire/tournament/partials/player-content.blade.php`):**
  - Focused 4-card essential specs row (`Format`, `1st Prize` + 2nd, `Platform`, `Result Window`), collapsible description, compact 2-column rules grid, and a clean 3-step `Match Timeline` (`Registration Opens`, `First Match`, `Estimated End`).



## Landing content and signed-in visits

The root URL uses the configured landing version for guests and signed-in users alike. Signed-in users get Dashboard links in place of signup actions.

In Admin → CMS → Landing, edit these sections:

- `hero`: title, subtitle, body, video, and secondary CTA. The `primary-action` item controls the signup button; the other active items supply the rotating headline phrases.
- `games`: heading, subtitle, description, and browse link.
- `how_it_works`, `stats`, and `top_players`: existing content and item labels; match statistics remain calculated from real activity.
- `faq`: heading, subtitle, body, and the small label (CTA Label). Items are the questions and answers. Hidden or deleted items stay hidden without template fallback questions.
- `activity`: activity strip title and empty-state message.
- `cta`: closing heading, body, signup action, and the sticky join-bar action.
- `competition_empty` and `leaderboard_empty`: copy and links shown when there is no competition or leaderboard activity.

Opening the landing editor or running `LandingPageSeeder` adds missing defaults without overwriting existing edits or restoring soft-deleted content. Footer copy and social links remain in the Footer editor.

Install buttons use the browser's native prompt when available. Otherwise, a dismissible modal shows manual installation instructions, including Share → Add to Home Screen for iPhone/iPad. The footer install button is also available on mobile. Actual device installation requires verification on physical Android and iOS devices.
