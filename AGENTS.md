# Repository Guidelines

## Project Structure & Module Organization
Towell is a Laravel 12 application.
- Backend code: `app/` (`Http/Controllers`, `Models`, `Services`, `Repositories`, `Imports`, `Exports`, `Helpers`).
- Routes: `routes/web.php`, `routes/public.php`, `routes/ai.php`, `routes/console.php`.
- Frontend assets: `resources/js`, `resources/css`, Blade views in `resources/views`, reusable templates in `resources/templates`.
- Database: `database/migrations`, `database/seeders`.
- Public/build output: `public/` (bundled via Vite).

## Build, Test, and Development Commands
- `composer install` and `npm install`: install backend/frontend dependencies.
- `composer dev`: run full local stack (Laravel server, queue listener, logs, Vite).
- `php artisan serve`: run only the Laravel server.
- `npm run dev`: run Vite dev server for assets.
- `npm run build`: production asset build.
- `php artisan test`: run PHPUnit suite.
- `vendor/bin/pint`: apply Laravel Pint formatting.

## Coding Style & Naming Conventions
- Follow PSR-12 and Laravel defaults (4-space indentation, UTF-8, one class per file).
- Class names: `PascalCase`; methods/variables: `camelCase`; config/env keys: `UPPER_SNAKE_CASE`.
- Keep controllers thin; move business logic to `Services`/`Repositories`.
- Use Form Requests for validation and Eloquent scopes for reusable query constraints.

## Testing Guidelines
- PHPUnit is configured in `phpunit.xml` with `Unit` and `Feature` suites.
- Place tests under `tests/Unit` or `tests/Feature` with names ending in `Test.php` (for example, `PlaneacionServiceTest.php`).
- Prefer feature tests for HTTP flows and permissions; unit test isolated services/helpers.
- Run `php artisan test` before opening a PR.

## Commit & Pull Request Guidelines
- Recent history uses short, task-focused Spanish messages (for example, `produccion urdido`, `logica de validacion...`). Keep commits concise and scoped.
- Recommended format: `<area>: <brief change>` (example: `planeacion: evitar duplicados en programa`).
- PRs should include: objective, affected modules/routes, migration impact, test evidence, and UI screenshots when views change.

## Security & Configuration Tips
- Never commit `.env` or credentials.
- This project targets SQL Server by default (`DB_CONNECTION=sqlsrv`); validate DB settings before migrations.
- Clear caches after config/routing updates: `php artisan optimize:clear`.
