# Repository Guidelines

## Project Structure & Module Organization

PlayerSaloons is a Laravel application with Livewire, Alpine.js, Tailwind CSS, Redis queues, and Reverb. Domain logic belongs in `app/Modules/{Domain}/` (for example, `Tournament`, `Match`, `Wallet`, and `Identity`). Keep operations in `Actions/`, persistence in `Models/`, and asynchronous reactions in `Events/`, `Listeners/`, or `Jobs/`. Livewire page components live in `app/Livewire/`; Blade templates and frontend sources are under `resources/views/`, `resources/js/`, and `resources/css/`. Routes are split between `routes/web.php`, `api.php`, and `console.php`. Database migrations, factories, and seeders live in `database/`. Tests are organized by domain in `tests/Feature/` and `tests/Unit/`; supporting architecture and flow documentation is in `documentation/`.

## Build, Test, and Development Commands

- `composer setup` installs dependencies, prepares `.env`, migrates, and builds assets.
- `composer run dev` starts the web server, queue listener, logs, Reverb, Vite, and scheduler together.
- `composer test` clears cached configuration and runs the PHPUnit suite.
- `php artisan test tests/Feature/Tournament/TournamentV2WorkflowTest.php` runs a focused test file.
- `npm run build` creates the production Vite bundle.
- `./vendor/bin/pint --test` checks PHP formatting; use `./vendor/bin/pint --dirty` to format changed PHP files.
- `./vendor/bin/phpstan analyse` runs Larastan at the configured level.

## Coding Style & Naming Conventions

Follow `.editorconfig`: UTF-8, LF endings, four-space indentation (two spaces for YAML), and final newlines. PHP follows Laravel Pint and PSR-4. Use descriptive PascalCase class names (`ResolveV2ResultTimeoutAction`) and camelCase methods. Tests use `test_descriptive_behavior` names. Actions must enforce their own authorization; events should carry identifiers, not Eloquent models. Route status changes through state machines and financial mutations through wallet/ledger services. Add user-facing copy to locale files.

## Testing Guidelines

Use PHPUnit with `RefreshDatabase` for database-backed behavior. Add feature tests for Livewire, HTTP, jobs, and workflows; use unit tests for state machines and isolated services. There is no numeric coverage gate, but every bug fix should include a regression test. CI runs against MySQL and Redis, builds assets, checks Pint, migrates, and executes the full suite.

## Commit & Pull Request Guidelines

Write imperative, focused commit subjects such as `Fix V2 tournament timeout recovery` or scoped Conventional Commit forms like `feat(matches): ...`. Keep unrelated changes in separate commits. Pull requests should explain the user-visible outcome, implementation risks, migrations or configuration changes, and verification commands. Link the issue when available and include screenshots for UI changes. Never commit `.env`, credentials, generated logs, or unrelated build artifacts.
