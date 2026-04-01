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

## Learned User Preferences

- En modales que ya tienen cierre con X, no duplicar botón "Cancelar" si el usuario prefiere cerrar solo con la X (patrón usado en flujos de programa de tejido).
- En tablas modales de programa de tejido: encabezados breves cuando se pida (por ejemplo "Saldos"), evitar badges recortados (`whitespace-nowrap` / ancho mínimo), y resaltar saldos negativos o modelo REPASO1 con badge rojo cuando aplique.
- En el detalle de telar dentro de modales de programa de tejido, mostrar las cifras como enteros redondeando al entero más cercano (fracción decimal ≥ 0,5 hacia arriba; si no, hacia abajo).

## Learned Workspace Facts

- En balanceo automático (`BalancearTejido`), el cierre del total frente al objetivo (sobra o falta) debe concentrarse en el **último** registro del grupo en orden `Posicion` / `NoTelarId`, sin repartir recortes en los demás telares del grupo.
- En clases bajo `App\...`, usar `use Carbon\Carbon` (o `CarbonInterface` para constantes como `MONDAY`) para evitar que `Carbon::...` se resuelva al namespace local y para satisfacer analizadores estáticos.
- Métodos PHP muy largos con muchas ramas (p. ej. `balancearAutomatico`) pueden disparar avisos del IDE del tipo "too many types"; extraer lógica a métodos privados y declarar retorno explícito (`JsonResponse`) suele aliviarlo.
