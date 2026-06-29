# PlayerSaloons — Start Here

PlayerSaloons is a modular Laravel 11 esports tournament platform. Players register, join tournaments, play matches, and get paid. Admins manage the full lifecycle through a dedicated panel.

---

## 🗂️ Where to Look

| You want to... | Read this |
|---|---|
| Understand the full feature set + all routes/components | `documentation/FEATURE_MAP.md` |
| Work on a specific module (Identity, Tournament, Match, Wallet, Team, Admin) | `documentation/01_` to `05_*.md` |
| Understand an architecture decision or why something was built a certain way | `documentation/architecture_baseline.md` |
| See what's not yet done or what needs to be built next | `documentation/execution_checklist.md` |
| See history of what was built and bug fixes | `documentation/project_progress.md` |
| Deploy to production | `documentation/project_progress.md` → Deployment Notes section |

**For AI context feeding** — feed only what's relevant to the task. `FEATURE_MAP.md` covers most cases. Avoid feeding `project_progress.md` unless you need change history context.

---

## 🚀 Local Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install && npm run dev
php artisan serve
```

Default seeded credentials (from `DatabaseSeeder`):
- **Super Admin**: check `PlatformSystemUserSeeder` for credentials
- **Player**: register via `/register`

---

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11+ |
| Frontend | Livewire 3 + Alpine.js |
| Realtime | Laravel Reverb (WebSockets) |
| PWA | Web manifest + service worker + native install prompt |
| CSS | Tailwind CSS v4 + Lucide Icons + custom esports design system |
| Fonts | Orbitron (headings/numbers) + Inter (body) — Google Fonts, preloaded in landing layout |
| Auth | Laravel session + Sanctum (API) |
| RBAC | Spatie Laravel Permission |
| Audit Log | Spatie Laravel Activity Log |
| Queue | Laravel Horizon (Redis) |
| Database | MySQL 8 (prod) / SQLite (local) |
| Cache/Session | Redis |
| File Storage | Local `public` disk (dev) → R2/S3 (prod, pending) |
| Testing | PHPUnit (Feature + Unit) |
| Static Analysis | PHPStan / Larastan Level 5–8 |
| Deployment | Docker Compose + Coolify |

---

## 📐 Key Conventions

### Module Structure
All business logic lives inside `app/Modules/{Domain}/`:
```
app/Modules/Identity/
  Actions/        ← one class per operation, self-authorizing
  Events/         ← thin, identifiers only (no Eloquent models)
  Listeners/      ← respond to events, queued where needed
  Models/
  Policies/
  StateMachines/
```

### Rules
- **Actions are self-authorizing** — role/policy guards go inside the Action, not only at the component level.
- **Events carry IDs only** — never pass Eloquent model instances into events.
- **State transitions go through StateMachines** — never mutate status directly on a model.
- **Finance is ledger-based** — `WalletService` for all debits/credits; `ledger_entries` is the source of truth.
- **Immutable models** (`LedgerEntry`, `MatchEvidence`, `Refund`, etc.) throw `LogicException` on update/delete.
- **Public shell is shared** — welcome and guest/public Livewire pages use `resources/views/components/layouts/partials/public-navigation.blade.php` and `public-footer.blade.php`. Keep public navigation/footer changes there.
- **Landing page content is table-backed** — `/` renders through `app/Livewire/Landing/LandingPage.php`; editable content lives in `landing_sections` and `landing_section_items`, with admin controls in `/admin/cms`.
- **Landing game cards use the game catalog** — active games come from `games` / `game_translations`; optional card banners are stored in `games.banner_path`.
- **Public navigation is table-backed** — public navbar links come from `public_navigation_items`, seeded by `PublicNavigationSeeder`, and are edited in `/admin/cms`.
- **Policy pages are table-backed and separate from CMS pages** — legal/policy content lives in `policy_pages`, is seeded by `PolicyPageSeeder`, is edited from `/admin/policies`, and renders publicly at `/policies` and `/policies/{slug}`.
- **All user-facing words must be translatable** — when adding any visible UI text, placeholder, tooltip/title, aria-label, email/notification copy, validation/session message, or seeded public content, add the English phrase as a key in `lang/en.json` and add matching entries to the supported locale files as translations become available. The rendered HTML translator can translate existing Blade text by exact key, but new text is not truly localized until the phrase exists in the locale JSON files.
- **Content-backed labels need locale records** — for games and CMS-style content, use existing translation tables (`game_translations`, CMS/page/policy/landing translation records where available) and read them through current-locale helpers or current-locale queries with English fallback. Do not hard-code `locale = en` for user-visible display except as a fallback.
- **Translation management lives in admin** — `/admin/translations` imports current `lang/*.json` phrases into `translation_strings`, lets staff fill missing locale text, and exports changes back to JSON. Use **Sync JSON** after code adds new file-based keys, edit/fill translations, then use **Save & Export** or **Export JSON** so runtime translation files are refreshed.
- **No page-local PWA scripts** — PWA install prompt, public burger menu behavior, service worker registration, and lazy Echo setup live in `resources/js/app.js`.
- **Echo/Reverb is authenticated-only on the frontend** — do not eagerly create `window.Echo` on guest/public pages; initialize it only when `meta[name="user-uuid"]` and Reverb env config exist.
- **Landing nav is fixed and scroll-aware** — `#public-nav` starts fully transparent over the hero video and transitions to a solid dark background on scroll via `initPublicNav()` in `app.js`. The `.nav-transparent` / `.nav-solid` CSS classes control this. On non-landing pages (no `.landing-hero` present), the nav always renders solid.
- **Mobile nav topbar is minimal** — on screens below `md` breakpoint, the public navbar topbar shows only the logo, Sign In, and Join Now (guests) or a Dashboard shortcut (authed users). All other links (nav items, install, profile, logout) live inside the burger dropdown at `[data-public-mobile-menu]`.
- **No horizontal scroll except the games carousel** — `html` and `body` both carry `overflow-x: hidden`. Sections with decorative orbs use `.landing-section-overflow-clip` (CSS `overflow-x: clip`). The only intentional horizontal scroll surface is `.landing-games-scroll` on the landing page games section.
- **Landing CSS design system** — all landing-specific styles are prefixed `landing-` in `app.css`. Key classes: `.landing-page-root`, `.landing-hero`, `.landing-main-pattern`, `.landing-section-overflow-clip`, `.landing-games-scroll`, `.landing-gradient-text`, `.landing-section-title`, `.landing-section-kicker`, `.landing-card`, `.landing-stat-card`, `.landing-cta-primary`, `.landing-fade-in` (+ delay variants), `.landing-top-glow`.

---

## ✅ Definition of Done

Every new feature, bug fix, or enhancement is only considered **done** when all of the following are true:

1. **PHPStan passes at Level 5 minimum** — run `./vendor/bin/phpstan analyse` before marking done. New code must not introduce errors. Level 8 is the target for core modules (Identity, Wallet, Tournament, Match).
2. **Test written and passing** — every new Action, Service, or state transition must have a corresponding test. Feature tests for Livewire/API, unit tests for StateMachines and Services.
3. **Localization checked** — every new user-readable word or content value is either already table-backed with locale support or has an English key in `lang/en.json` plus entries/placeholders in the supported locale JSON files (`fr`, `es`, `de`, `it`, `nl`, `pt`, `ru`, `ja`, `zh`, `pl`). Include placeholders, buttons, empty states, flash/session messages, validation text, aria labels, titles/tooltips, navigation labels, and notification/email copy.
4. **Docs updated** — relevant doc files updated per the conventions below.

```bash
# Run before marking anything done
./vendor/bin/phpstan analyse
php artisan test
```

---

## 📝 Documentation Update Conventions

**When to update which file:**

| Change type | Update here |
|---|---|
| New feature built | `FEATURE_MAP.md` (add route + component + backend mapping) + relevant `0X_module.md` |
| Bug fix | `project_progress.md` (new version entry) |
| Architecture decision changed | `architecture_baseline.md` (new entry under Post-v1.14 Changes with rationale) |
| New post-MVP task identified | `execution_checklist.md` |
| Test written for a pending item | `execution_checklist.md` (check the box) |
| Deployment issue/fix | `project_progress.md` → Deployment Notes section |

**Format for `project_progress.md` entries:**
```markdown
## ✅ [Short description] (v{version})

- **`ClassName`**: What changed and why.
- **Tests**: X new tests, all passing.
- **PHPStan**: 0 errors at Level X.
```

**Version bumping**: Increment patch version (e.g., v1.28 → v1.29) for any change. Update `Last Updated` date at the top of `project_progress.md`.

**Format for `architecture_baseline.md` entries:**
```markdown
### [{version}] Short title

**Baseline reference**: Which original pattern this relates to.

**What changed**: Description of the change.

**Why**: Rationale — what problem it solved or what the baseline assumed that was wrong.
```

---

## 🐛 Tracking Features, Bugs & Enhancements

All new ideas, recommended features, bugs, and enhancements go into `execution_checklist.md` under the appropriate section. **Never leave them only in chat, comments, or memory.**

| Type | Where to log it | Format |
|---|---|---|
| Bug found | `execution_checklist.md` → `## 🐛 Known Bugs` | `- [ ] Short description — file/component affected` |
| Enhancement idea | `execution_checklist.md` → `## 🛠️ Other Post-MVP Tasks` | `- [ ] Short description` |
| Recommended feature | `execution_checklist.md` → appropriate section | `- [ ] Feature name — brief rationale` |
| Testing debt | `execution_checklist.md` → `## 🧪 Testing Debt` | `- [ ] test_method_name — what it verifies` |
| Architecture concern | `architecture_baseline.md` → new entry | See format above |

When something is built from the checklist:
1. Check the box `[x]` in `execution_checklist.md`
2. Add a `## ✅ [description] (v{version})` entry to `project_progress.md`
3. Update `FEATURE_MAP.md` if it's a new route, component, or backend mapping
4. Update the relevant `documentation/0X_module.md` if the user flow changed

**Keeping everything in sync** — the single source of truth per concern:
- *What exists and where* → `FEATURE_MAP.md`
- *Why it was built that way* → `architecture_baseline.md`
- *What happened and when* → `project_progress.md`
- *What's next* → `execution_checklist.md`
- *How to work on it* → `documentation/0X_module.md`

If information exists in two places, the more specific file wins (e.g., module doc overrides FEATURE_MAP for flow details).

---

## 💾 Where Data Is Stored

| Data | Storage | Notes |
|---|---|---|
| All app data | MySQL 8 (`mysql` container) | Persistent via `mysql_data` Docker volume |
| Sessions | Redis (`redis` container) | `SESSION_DRIVER=redis` |
| Cache | Redis | `CACHE_STORE=redis` |
| Queue jobs | Redis + Horizon | `QUEUE_CONNECTION=redis` |
| User uploaded files | Local `public` disk (dev) | `storage/app/public/` — **not** persisted across deploys without a volume |
| Landing page content | `landing_sections`, `landing_section_items` | Hero copy/video path, section headings, editable cards, reviews, stat labels, and footer links |
| Landing game banners | `games.banner_path` | Optional public path for the homepage game carousel card image |
| Public navigation | `public_navigation_items` | Editable public navbar labels, URLs, icons, visibility rules, order, and active state |
| Policy pages | `policy_pages` | Terms, cookie, privacy, refund/cancellation, and disclaimer content editable from `/admin/policies` |
| UI translations | `translation_strings` + `lang/*.json` | Admin-managed from `/admin/translations`; database is the editing source, JSON files are the runtime/export cache |
| Public file access | `public/storage` symlink | Created by `php artisan storage:link` |
| Audit logs | `activity_log` DB table | Spatie Activity Log |
| App configuration | `.env` (never commit) | Use `.env.production.example` as template |

**Production file storage** is currently local (pending R2/S3 migration). Files will be lost on redeploy unless a Docker volume is mounted at `storage/app/public`. See `execution_checklist.md` → File Storage Migration.
