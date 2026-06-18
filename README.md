# PlayerSaloons

Esports tournament platform built on Laravel 11. Players register, join tournaments, compete in bracket-based matches, and receive payouts. Admins manage the full lifecycle through a dedicated operations panel.

## Documentation

**Start here** → [`documentation/ONBOARDING.md`](documentation/ONBOARDING.md)

| Doc | Purpose |
|---|---|
| [`ONBOARDING.md`](documentation/ONBOARDING.md) | Local setup, conventions, where to look |
| [`FEATURE_MAP.md`](documentation/FEATURE_MAP.md) | All routes, components, backend mapping, test coverage |
| [`architecture_baseline.md`](documentation/architecture_baseline.md) | Architecture decisions and rationale |
| [`execution_checklist.md`](documentation/execution_checklist.md) | Post-MVP backlog and testing debt |
| [`project_progress.md`](documentation/project_progress.md) | Version history, bug fixes, deployment notes |
| `documentation/01–05_*.md` | Per-module flow docs (Identity, Tournament, Match, Wallet, Team, Admin) |

## Quick Start

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install && npm run dev
php artisan serve
```

## Tech Stack

Laravel 11 · Livewire 3 · Alpine.js · Tailwind CSS v4 · Laravel Reverb · Horizon · MySQL · Redis · PHPUnit · PHPStan
